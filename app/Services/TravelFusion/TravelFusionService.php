<?php

namespace App\Services\TravelFusion;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Services\TravelFusion\Requests\LoginRequestBuilder;
use SimpleXMLElement;
use Illuminate\Support\Facades\Log;

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
    public function sendRequest(array $requestData, string $type = 'default'): array
    {
        $loginId = Cache::remember('travelfusion_login_id', now()->addDay(), function () {
            return $this->login();
        });

        // Inject LoginId and XmlLoginId
        $this->injectLoginIds($requestData, $loginId);

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

        return $this->makeRequest($this->baseUrl, $xmlContent);
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    private function makeRequest(string $endpoint, string $xmlContent): array
    {
        // Log request in beautiful XML format
        $this->logRequest($endpoint, $xmlContent);

        $response = Http::withHeaders([
            'Accept' => 'text/xml',
            'Accept-Encoding' => 'gzip, deflate',
            'Content-Type' => 'text/xml; charset=utf-8'
        ])
            ->retry(3, 5000)
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
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Request></Request>');

        // Add endpoint information
        $xml->addChild('Endpoint', $endpoint);
        $xml->addChild('Timestamp', now()->toIso8601String());

        // Add request data
        $requestData = $xml->addChild('Data');
        $requestData->addChild('XmlContent', htmlspecialchars($xmlContent));

        // Format XML for better readability
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        $formattedXml = $dom->saveXML();

        Log::channel('travelfusion')->info("Request to {$endpoint}:\n" . $formattedXml);
    }

    private function logResponse(string $endpoint, string $responseBody): void
    {
        try {
            // Try to parse and format the XML response
            $xml = new \SimpleXMLElement($responseBody);
            $dom = dom_import_simplexml($xml)->ownerDocument;
            $dom->formatOutput = true;
            $formattedXml = $dom->saveXML();

            Log::channel('travelfusion')->info("Response from {$endpoint}:\n" . $formattedXml);
        } catch (\Exception $e) {
            // If response is not valid XML, log it as is
            Log::channel('travelfusion')->info("Response from {$endpoint}:\n" . $responseBody);
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
                    $subNode = $xmlData->addChild($key);
                    $this->arrayToXml($value, $subNode);
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

