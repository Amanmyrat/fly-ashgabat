<?php

namespace App\Services\TravelFusion;

use App\Models\TravelFusionPassword;
use App\Services\TravelFusion\RequestBuilder\LoginRequestBuilder;
use App\Services\TravelFusion\RequestBuilder\NewPasswordRequestBuilder;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class TravelFusionService
{
    private string $baseUrl;

    private ?string $username = null;
    private ?string $password = null;
    private ?string $loginId = null;

    public function __construct()
    {
        $this->baseUrl = config('services.travelfusion.base_url', 'https://api.travelfusion.com');
    }

    /**
     * Load active TravelFusion credentials only when actually needed.
     *
     * @throws Exception
     */
    private function loadCredentials(bool $forceReload = false): void
    {
        if (
            !$forceReload &&
            $this->username !== null &&
            $this->password !== null
        ) {
            return;
        }

        $activePassword = TravelFusionPassword::where('is_active', true)->first();

        if (!$activePassword) {
            throw new Exception('No active TravelFusion password found');
        }

        $this->username = $activePassword->username;
        $this->password = $activePassword->password;
        $this->loginId = $activePassword->login_id;
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function login(): string
    {
        $this->loadCredentials();

        $builder = new LoginRequestBuilder($this->username, $this->password);
        $requestData = $builder->build();

        $xmlRoot = new SimpleXMLElement('<CommandList/>');
        $this->arrayToXml($requestData, $xmlRoot);
        $xmlContent = $xmlRoot->asXML();

        $response = $this->makeRequest($this->baseUrl, $xmlContent);

        if (isset($response['Login']['LoginId'])) {
            $loginId = $response['Login']['LoginId'];

            TravelFusionPassword::where('is_active', true)
                ->where('username', $this->username)
                ->update([
                    'login_id' => $loginId,
                ]);

            $this->loginId = $loginId;

            return $loginId;
        }

        throw new Exception('Failed to log in to TravelFusion');
    }

    /**
     * Change the TravelFusion API password.
     *
     * @throws ConnectionException
     * @throws Exception
     */
    public function changePassword(string $newPassword): void
    {
        $this->loadCredentials();

        if (!$this->validatePassword($newPassword)) {
            throw new Exception('Password does not meet requirements');
        }

        $builder = new NewPasswordRequestBuilder(
            $this->username,
            $this->password,
            $newPassword
        );

        $requestData = $builder->build();

        $xmlRoot = new SimpleXMLElement('<CommandList/>');
        $this->arrayToXml($requestData, $xmlRoot);
        $xmlContent = $xmlRoot->asXML();

        $response = $this->makeRequest($this->baseUrl, $xmlContent);

        if (
            !isset($response['NewPassword']['Success']) ||
            $response['NewPassword']['Success'] !== 'true'
        ) {
            throw new Exception('Failed to change password');
        }

        TravelFusionPassword::where('is_active', true)
            ->where('username', $this->username)
            ->update([
                'is_active' => false,
            ]);

        TravelFusionPassword::create([
            'username' => $this->username,
            'password' => $newPassword,
            'changed_at' => now(),
            'expires_at' => now()->addDays(90),
            'is_active' => true,
            'login_id' => null,
        ]);

        $this->password = $newPassword;
        $this->loginId = null;

        // Get fresh LoginId after password change.
        $this->login();
    }

    private function validatePassword(string $password): bool
    {
        if (strlen($password) < 8 || strlen($password) > 20) {
            return false;
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        if (!preg_match('/[?!@#$%^+\-_=]/', $password)) {
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9?!@#$%^+\-_=]+$/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function sendRequest(array $requestData, string $type = 'default'): array
    {
        $this->loadCredentials();

        if (!$this->loginId) {
            $this->loginId = $this->login();
        }

        $this->injectLoginIds($requestData, $this->loginId);

        $xmlContent = $this->buildXmlContent($requestData, $type);
        $shouldRetry = !$this->isOneTimeRequest($requestData);

        try {
            return $this->makeRequest($this->baseUrl, $xmlContent, $shouldRetry);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'LoginId')) {
                $this->loginId = $this->login();

                $this->injectLoginIds($requestData, $this->loginId);

                // Rebuild XML after injecting the new LoginId.
                $xmlContent = $this->buildXmlContent($requestData, $type);

                return $this->makeRequest($this->baseUrl, $xmlContent, $shouldRetry);
            }

            throw $e;
        }
    }

    private function buildXmlContent(array $requestData, string $type = 'default'): string
    {
        $xmlRoot = new SimpleXMLElement('<CommandList/>');

        switch ($type) {
            case 'processTerms':
                $this->arrayToXmlProcessTerms($requestData, $xmlRoot);
                break;

            default:
                $this->arrayToXml($requestData, $xmlRoot);
                break;
        }

        return $xmlRoot->asXML();
    }

    private function isOneTimeRequest(array $requestData): bool
    {
        return isset($requestData['GetBookingDetails']) ||
            isset($requestData['CheckBooking']) ||
            isset($requestData['StartBooking']);
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    private function makeRequest(
        string $endpoint,
        string $xmlContent,
        bool $shouldRetry = true
    ): array {
        $this->logRequest($endpoint, $xmlContent);

        $httpClient = Http::withHeaders([
            'Accept' => 'text/xml',
            'Accept-Encoding' => 'gzip, deflate',
            'Content-Type' => 'text/xml; charset=utf-8',
        ]);

        if ($shouldRetry) {
            $httpClient = $httpClient->retry(3, 5000);
        }

        $response = $httpClient
            ->withoutVerifying()
            ->timeout(120)
            ->withBody($xmlContent, 'text/xml')
            ->post($endpoint);

        $this->logResponse($endpoint, $response->body());

        if ($response->successful()) {
            $responseXml = simplexml_load_string($response->body());

            if ($responseXml === false) {
                throw new Exception('TravelFusion returned invalid XML response');
            }

            return json_decode(json_encode($responseXml), true);
        }

        throw new Exception('TravelFusion request failed: ' . $response->body());
    }

    private function logRequest(string $endpoint, string $xmlContent): void
    {
        try {
            $requestXml = new SimpleXMLElement($xmlContent);
            $dom = dom_import_simplexml($requestXml)->ownerDocument;

            $dom->formatOutput = true;
            $dom->preserveWhiteSpace = false;

            $formattedRequestXml = $dom->saveXML();

            $formattedRequestXml = preg_replace('/\n\s*\n/', "\n", $formattedRequestXml);
            $formattedRequestXml = preg_replace('/\s{2,}/', ' ', $formattedRequestXml);

            Log::channel('travelfusion')->info("Request to {$endpoint}");
            Log::channel('travelfusion')->info($formattedRequestXml);
        } catch (Exception $e) {
            Log::channel('travelfusion')->info("Request to {$endpoint}");
            Log::channel('travelfusion')->info($xmlContent);
        }
    }

    private function logResponse(string $endpoint, string $responseBody): void
    {
        try {
            $xml = new SimpleXMLElement($responseBody);
            $dom = dom_import_simplexml($xml)->ownerDocument;

            $dom->formatOutput = true;

            $formattedXml = $dom->saveXML();

            Log::channel('travelfusion')->info("Response from {$endpoint}");
            Log::channel('travelfusion')->info($formattedXml);
        } catch (Exception $e) {
            Log::channel('travelfusion')->info("Response from {$endpoint}");
            Log::channel('travelfusion')->info($responseBody);
        }
    }

    private function injectLoginIds(array &$requestData, string $loginId): void
    {
        foreach ($requestData as &$value) {
            if (is_array($value)) {
                $value['LoginId'] = $loginId;
                $value['XmlLoginId'] = $loginId;
                break;
            }
        }
    }

    private function arrayToXml(array $data, SimpleXMLElement $xmlData): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $this->arrayToXml($value, $xmlData);
                    continue;
                }

                if ($key === 'CustomSupplierParameterList') {
                    $subNode = $xmlData->addChild($key);

                    if (isset($value['CustomSupplierParameter']['Name'])) {
                        $param = $value['CustomSupplierParameter'];

                        $paramNode = $subNode->addChild('CustomSupplierParameter');
                        $paramNode->addChild('Name', htmlspecialchars($param['Name']));
                        $paramNode->addChild('Value', htmlspecialchars($param['Value']));
                    } else {
                        foreach ($value['CustomSupplierParameter'] as $param) {
                            $paramNode = $subNode->addChild('CustomSupplierParameter');
                            $paramNode->addChild('Name', htmlspecialchars($param['Name']));
                            $paramNode->addChild('Value', htmlspecialchars($param['Value']));
                        }
                    }

                    continue;
                }

                $subNode = $xmlData->addChild($key);
                $this->arrayToXml($value, $subNode);

                continue;
            }

            $xmlData->addChild($key, htmlspecialchars((string) $value));
        }
    }

    private function arrayToXmlProcessTerms(array $data, SimpleXMLElement $xmlData): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                continue;
            }

            if (is_array($value)) {
                if ($key === 'TravellerList') {
                    $subNode = $xmlData->addChild($key);

                    foreach ($value['Traveller'] as $traveller) {
                        $travellerNode = $subNode->addChild('Traveller');
                        $this->arrayToXmlProcessTerms($traveller, $travellerNode);
                    }

                    continue;
                }

                if ($key === 'NamePartList') {
                    $subNode = $xmlData->addChild($key);

                    foreach ($value['NamePart'] as $namePart) {
                        $subNode->addChild('NamePart', htmlspecialchars((string) $namePart));
                    }

                    continue;
                }

                if ($key === 'CustomSupplierParameterList') {
                    $subNode = $xmlData->addChild($key);

                    if (isset($value['CustomSupplierParameter']['Name'])) {
                        $param = $value['CustomSupplierParameter'];

                        $paramNode = $subNode->addChild('CustomSupplierParameter');
                        $paramNode->addChild('Name', htmlspecialchars($param['Name']));
                        $paramNode->addChild('Value', htmlspecialchars($param['Value']));
                    } else {
                        foreach ($value['CustomSupplierParameter'] as $param) {
                            $paramNode = $subNode->addChild('CustomSupplierParameter');
                            $paramNode->addChild('Name', htmlspecialchars($param['Name']));
                            $paramNode->addChild('Value', htmlspecialchars($param['Value']));
                        }
                    }

                    continue;
                }

                $subNode = $xmlData->addChild($key);
                $this->arrayToXmlProcessTerms($value, $subNode);

                continue;
            }

            $xmlData->addChild($key, htmlspecialchars((string) $value));
        }
    }
}
