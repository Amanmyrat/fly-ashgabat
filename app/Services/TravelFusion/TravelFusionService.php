<?php

namespace App\Services\TravelFusion;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use App\Services\TravelFusion\Requests\LoginRequestBuilder;
use App\Services\TravelFusion\Requests\NewPasswordRequestBuilder;
use App\Models\TravelFusionPassword;
use SimpleXMLElement;
use Illuminate\Support\Facades\Log;

class TravelFusionService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private ?string $loginId = null;

    public function __construct()
    {
        $this->baseUrl = config('services.travelfusion.base_url', 'https://api.travelfusion.com');

        // Get credentials from the active password record
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
        $builder = new LoginRequestBuilder($this->username, $this->password);
        $requestData = $builder->build();

        $xmlRoot = new SimpleXMLElement('<CommandList/>');
        $this->arrayToXml($requestData, $xmlRoot);
        $xmlContent = $xmlRoot->asXML();

        $response = $this->makeRequest($this->baseUrl, $xmlContent);

        if (isset($response['Login']['LoginId'])) {
            $loginId = $response['Login']['LoginId'];

            // Update the login_id in the database
            TravelFusionPassword::where('is_active', true)
                ->where('username', $this->username)
                ->update(['login_id' => $loginId]);

            $this->loginId = $loginId;
            return $loginId;
        }

        throw new Exception('Failed to log in to TravelFusion');
    }

    /**
     * Change the TravelFusion API password
     *
     * @param string $newPassword The new password to set
     * @throws ConnectionException
     * @throws Exception
     */
    public function changePassword(string $newPassword): void
    {
        // Validate password requirements
        if (!$this->validatePassword($newPassword)) {
            throw new Exception('Password does not meet requirements');
        }

        $builder = new NewPasswordRequestBuilder($this->username, $this->password, $newPassword);
        $requestData = $builder->build();

        $xmlRoot = new SimpleXMLElement('<CommandList/>');
        $this->arrayToXml($requestData, $xmlRoot);
        $xmlContent = $xmlRoot->asXML();

        $response = $this->makeRequest($this->baseUrl, $xmlContent);

        if (!isset($response['NewPassword']['Success']) || $response['NewPassword']['Success'] !== 'true') {
            throw new Exception('Failed to change password');
        }

        // Create new password record
        TravelFusionPassword::create([
            'username' => $this->username,
            'password' => $newPassword,
            'changed_at' => now(),
            'expires_at' => now()->addDays(90),
            'is_active' => true,
        ]);

        // Deactivate old password
        TravelFusionPassword::where('is_active', true)
            ->where('username', $this->username)
            ->update(['is_active' => false]);

        // Update the password in the service
        $this->password = $newPassword;
        $this->loginId = null; // Reset login ID as it will be invalid after password change

        // Get new LoginId after password change
        $this->login();
    }

    /**
     * Validate password meets TravelFusion requirements
     */
    private function validatePassword(string $password): bool
    {
        // Password must be between 8 and 20 characters
        if (strlen($password) < 8 || strlen($password) > 20) {
            return false;
        }

        // Password must contain at least 1 capital letter, 1 lower letter, 1 number and 1 special character
        if (!preg_match('/[A-Z]/', $password)) return false;
        if (!preg_match('/[a-z]/', $password)) return false;
        if (!preg_match('/[0-9]/', $password)) return false;
        if (!preg_match('/[?!@#$%^+\-_=]/', $password)) return false;

        // Only allow numbers, letters and specific special characters
        if (!preg_match('/^[a-zA-Z0-9?!@#$%^+\-_=]+$/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * @throws ConnectionException
     */
    public function sendRequest(array $requestData, string $type = 'default'): array
    {
        // Get login ID from model or login if not available
        if (!$this->loginId) {
            $this->loginId = $this->login();
        }

        // Inject LoginId and XmlLoginId
        $this->injectLoginIds($requestData, $this->loginId);

        $xmlRoot = new SimpleXMLElement('<CommandList/>');
        switch ($type) {
            case 'processTerms':
                $this->arrayToXmlProcessTerms($requestData, $xmlRoot);
                break;
            default:
                $this->arrayToXml($requestData, $xmlRoot);
                break;
        }

        $xmlContent = $xmlRoot->asXML();

        // Determine if retries should be disabled for this request type
        $shouldRetry = !$this->isOneTimeRequest($requestData);

        try {
            return $this->makeRequest($this->baseUrl, $xmlContent, $shouldRetry);
        } catch (Exception $e) {
            // If the request fails, try to login again and retry once
            if (str_contains($e->getMessage(), 'LoginId')) {
                $this->loginId = $this->login();
                $this->injectLoginIds($requestData, $this->loginId);
                return $this->makeRequest($this->baseUrl, $xmlContent, $shouldRetry);
            }
            throw $e;
        }
    }

    /**
     * Check if this is a request type that should only be called once
     */
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
    private function makeRequest(string $endpoint, string $xmlContent, bool $shouldRetry = true): array
    {
        // Log request in beautiful XML format
        $this->logRequest($endpoint, $xmlContent);

        $httpClient = Http::withHeaders([
            'Accept' => 'text/xml',
            'Accept-Encoding' => 'gzip, deflate',
            'Content-Type' => 'text/xml; charset=utf-8'
        ]);

        // Only add retry if requested
        if ($shouldRetry) {
            $httpClient = $httpClient->retry(3, 5000);
        }

        $response = $httpClient
            ->withoutVerifying()
            ->timeout(120)
            ->withBody($xmlContent, 'text/xml')->post($endpoint);

        // Log response in beautiful XML format
        $this->logResponse($endpoint, $response->body());

        if ($response->successful()) {
            $responseXml = simplexml_load_string($response->body());
            return json_decode(json_encode($responseXml), true);
        }

        throw new Exception('TravelFusion request failed: ' . $response->body());
    }

    private function logRequest(string $endpoint, string $xmlContent): void
    {
        // Parse and format the request XML
        $requestXml = new \SimpleXMLElement($xmlContent);
        $dom = dom_import_simplexml($requestXml)->ownerDocument;
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;
        $formattedRequestXml = $dom->saveXML();

        // Clean up excessive newlines and spaces
        $formattedRequestXml = preg_replace('/\n\s*\n/', "\n", $formattedRequestXml);
        $formattedRequestXml = preg_replace('/\s{2,}/', ' ', $formattedRequestXml);

        Log::channel('travelfusion')->info("Request to {$endpoint}");
        Log::channel('travelfusion')->info($formattedRequestXml);
    }

    private function logResponse(string $endpoint, string $responseBody): void
    {
        try {
            // Try to parse and format the XML response
            $xml = new \SimpleXMLElement($responseBody);
            $dom = dom_import_simplexml($xml)->ownerDocument;
            $dom->formatOutput = true;
            $formattedXml = $dom->saveXML();

            Log::channel('travelfusion')->info("Response from {$endpoint}");
            Log::channel('travelfusion')->info($formattedXml);
        } catch (\Exception $e) {
            // If response is not valid XML, log it as is
            Log::channel('travelfusion')->info("Response from {$endpoint}");
            Log::channel('travelfusion')->info($responseBody);
        }
    }

    /**
     * Inject LoginId and XmlLoginId into the request.
     */
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

    private function arrayToXml(array $data, \SimpleXMLElement $xmlData): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $this->arrayToXml($value, $xmlData);
                } else {
                    // Special handling for CustomSupplierParameterList
                    if ($key === 'CustomSupplierParameterList') {
                        $subNode = $xmlData->addChild($key);
                        // Check if CustomSupplierParameter is a single associative array
                        if (isset($value['CustomSupplierParameter']['Name'])) {
                            $param = $value['CustomSupplierParameter'];
                            $paramNode = $subNode->addChild('CustomSupplierParameter');
                            $paramNode->addChild('Name', htmlspecialchars($param['Name']));
                            $paramNode->addChild('Value', htmlspecialchars($param['Value']));
                        } else {
                            // Handle multiple CustomSupplierParameter entries
                            foreach ($value['CustomSupplierParameter'] as $param) {
                                $paramNode = $subNode->addChild('CustomSupplierParameter');
                                $paramNode->addChild('Name', htmlspecialchars($param['Name']));
                                $paramNode->addChild('Value', htmlspecialchars($param['Value']));
                            }
                        }
                    } else {
                        $subNode = $xmlData->addChild($key);
                        $this->arrayToXml($value, $subNode);
                    }
                }
            } else {
                $xmlData->addChild($key, htmlspecialchars($value));
            }
        }
    }

    private function arrayToXmlProcessTerms(array $data, \SimpleXMLElement $xmlData): void
    {
        foreach ($data as $key => $value) {
            // Skip numeric keys (they are used for array indexing, not XML tags)
            if (is_numeric($key)) {
                continue;
            }

            // Handle nested arrays
            if (is_array($value)) {
                // Special handling for TravellerList
                if ($key === 'TravellerList') {
                    $subNode = $xmlData->addChild($key);
                    foreach ($value['Traveller'] as $traveller) {
                        $travellerNode = $subNode->addChild('Traveller');
                        $this->arrayToXmlProcessTerms($traveller, $travellerNode);
                    }
                } // Special handling for NamePartList
                elseif ($key === 'NamePartList') {
                    $subNode = $xmlData->addChild($key);
                    foreach ($value['NamePart'] as $namePart) {
                        $subNode->addChild('NamePart', htmlspecialchars($namePart));
                    }
                } // Special handling for CustomSupplierParameterList
                elseif ($key === 'CustomSupplierParameterList') {
                    $subNode = $xmlData->addChild($key);
                    // Check if CustomSupplierParameter is a single associative array
                    if (isset($value['CustomSupplierParameter']['Name'])) {
                        $param = $value['CustomSupplierParameter'];
                        $paramNode = $subNode->addChild('CustomSupplierParameter');
                        $paramNode->addChild('Name', htmlspecialchars($param['Name']));
                        $paramNode->addChild('Value', htmlspecialchars($param['Value']));
                    } else {
                        // Handle multiple CustomSupplierParameter entries
                        foreach ($value['CustomSupplierParameter'] as $param) {
                            $paramNode = $subNode->addChild('CustomSupplierParameter');
                            $paramNode->addChild('Name', htmlspecialchars($param['Name']));
                            $paramNode->addChild('Value', htmlspecialchars($param['Value']));
                        }
                    }
                } // Recursively handle other nested arrays
                else {
                    $subNode = $xmlData->addChild($key);
                    $this->arrayToXmlProcessTerms($value, $subNode);
                }
            } else {
                // Handle simple key-value pairs
                if ($value === '') {
                    // Use <key></key> instead of <key/>
                    $xmlData->addChild($key, '');
                } else {
                    $xmlData->addChild($key, htmlspecialchars($value));
                }
            }
        }
    }
}

