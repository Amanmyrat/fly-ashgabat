<?php

namespace App\Services\TravelFusion;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Services\TravelFusion\Requests\LoginRequestBuilder;
use SimpleXMLElement;

class TravelFusionService
{
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->baseUrl = config('services.travelfusion.base_url', 'https://api.travelfusion.com');
        $this->username = config('services.travelfusion.username', env('TRAVELFUSION_USERNAME'));
        $this->password = config('services.travelfusion.password', env('TRAVELFUSION_PASSWORD'));
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
            return $response['Login']['LoginId'];
        }

        throw new Exception('Failed to log in to TravelFusion');
    }

    /**
     * @throws ConnectionException
     */
    public function sendRequest(array $requestData): array
    {
        $loginId = Cache::remember('travelfusion_login_id', now()->addDay(), function () {
            return $this->login();
        });

        // Inject LoginId and XmlLoginId
        $this->injectLoginIds($requestData, $loginId);

        $xmlRoot = new SimpleXMLElement('<CommandList/>');
        $this->arrayToXml($requestData, $xmlRoot);
        $xmlContent = $xmlRoot->asXML();

        return $this->makeRequest($this->baseUrl, $xmlContent);
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    private function makeRequest(string $endpoint, string $xmlContent): array
    {
        $response = Http::withHeaders([
            'Accept' => 'text/xml',
            'Content-Type' => 'text/xml; charset=utf-8',
        ])->withBody($xmlContent, 'text/xml')->post($endpoint);

        if ($response->successful()) {
            $responseXml = simplexml_load_string($response->body());
            return json_decode(json_encode($responseXml), true);
        }

        throw new Exception('TravelFusion request failed: ' . $response->body());
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
                    $subNode = $xmlData->addChild($key);
                    $this->arrayToXml($value, $subNode);
                }
            } else {
                $xmlData->addChild($key, htmlspecialchars($value));
            }
        }
    }

}
