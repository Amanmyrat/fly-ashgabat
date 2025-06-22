<?php

namespace App\Services\XMLAgency;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XMLAgencyService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('xmlagency.base_url');
    }

    /**
     * @throws \Exception
     */
    public function sendRequest(array $requestData, string $soapAction): array
    {
        $xmlContent = $this->buildSoapXml($requestData);
        return $this->makeRequest($this->baseUrl . config('xmlagency.search_endpoint'), $xmlContent, $soapAction);
    }

    private function buildSoapXml(array $requestData): string
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;

        // Create SOAP envelope
        $envelope = $dom->createElementNS('http://www.w3.org/2003/05/soap-envelope', 's:Envelope');
        $dom->appendChild($envelope);

        $body = $dom->createElement('s:Body');
        $envelope->appendChild($body);

        // Convert array to XML
        $this->arrayToXml($requestData, $body, $dom);

        return $dom->saveXML();
    }

    private function arrayToXml(array $data, DOMElement $parent, DOMDocument $dom): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $this->arrayToXml($value, $parent, $dom);
                } else {
                    $element = $this->createElement($key, $parent, $dom);
                    $this->arrayToXml($value, $element, $dom);
                }
            } else {
                $this->createTextElement($key, $value, $parent, $dom);
            }
        }
    }

    private function createElement(string $name, DOMElement $parent, DOMDocument $dom): DOMElement
    {
        // Handle special XML Agency methods with proper namespaces
        if (in_array($name, ['AeroSearch', 'AeroBook', 'ConfirmBook', 'AeroCancel', 'AeroTicket'])) {
            $element = $dom->createElementNS('http://tempuri.org/', $name);
            $parent->appendChild($element);
            return $element;
        }

        // Handle credentials block
        if ($name === 'credentials') {
            $element = $dom->createElement($name);
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:a', 'http://schemas.datacontract.org/2004/07/SiteCity.Common');
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:i', 'http://www.w3.org/2001/XMLSchema-instance');
            $parent->appendChild($element);
            return $element;
        }

        // Handle search params block
        if ($name === 'aeroSearchParams') {
            $element = $dom->createElement($name);
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:a', 'http://schemas.datacontract.org/2004/07/SiteCity.Avia.Search');
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:i', 'http://www.w3.org/2001/XMLSchema-instance');
            $parent->appendChild($element);
            return $element;
        }

        // Handle elements that need 'a:' prefix
        if ($this->needsPrefix($name, $parent)) {
            $element = $dom->createElement('a:' . $name);
            $parent->appendChild($element);
            return $element;
        }

        // Default case
        $element = $dom->createElement($name);
        $parent->appendChild($element);
        return $element;
    }

    private function createTextElement(string $name, $value, DOMElement $parent, DOMDocument $dom): void
    {
        if ($value === null) {
            $element = $this->createElement($name, $parent, $dom);
            $element->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'i:nil', 'true');
        } else {
            $element = $this->createElement($name, $parent, $dom);
            $element->textContent = $value;
        }
    }

        private function needsPrefix(string $name, DOMElement $parent): bool
    {
        // Elements that need 'a:' prefix based on their parent
        $parentName = $parent->localName ?? $parent->nodeName;

        if ($parentName === 'credentials') {
            return in_array($name, ['ApiLogin', 'ApiPassword', 'AuthExtendedData', 'Currency', 'DeviceId', 'Language', 'TokenGuid']);
        }

        if ($parentName === 'aeroSearchParams') {
            return in_array($name, ['Adults', 'Childs', 'ExtendedParams', 'FlightClass', 'Infants', 'PartnerName', 'SearchFlights']);
        }

        if ($parentName === 'SearchFlights' || $parentName === 'a:SearchFlights') {
            return $name === 'SearchFlight';
        }

        if ($parentName === 'SearchFlight' || $parentName === 'a:SearchFlight') {
            return in_array($name, ['Date', 'IATAFrom', 'IATATo']);
        }

        return false;
    }

    private function makeRequest(string $endpoint, string $xmlContent, string $soapAction): array
    {
        $actionUrl = config('xmlagency.soap_actions.' . $soapAction);

        if (!$actionUrl) {
            throw new \Exception("Unknown SOAP action: {$soapAction}");
        }

        Log::info('XML Agency Request', ['xml' => $xmlContent, 'action' => $soapAction, 'action_url' => $actionUrl]);

        $response = Http::timeout(config('xmlagency.timeout'))
            ->withHeaders([
                'Content-Type' => 'application/soap+xml; charset=utf-8; action="' . $actionUrl . '"',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'Keep-Alive',
                'Host' => parse_url($endpoint, PHP_URL_HOST)
            ])
            ->send('POST', $endpoint, [
                'body' => $xmlContent
            ]);
        Log::info('XML Agency Response', ['body' => $response->body()]);

        if ($response->successful()) {
            $responseXml = simplexml_load_string($response->body());
            return json_decode(json_encode($responseXml), true);
        }

        throw new \Exception('XML Agency request failed: ' . $response->body());
    }
}
