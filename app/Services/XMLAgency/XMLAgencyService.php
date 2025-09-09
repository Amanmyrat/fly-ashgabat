<?php

namespace App\Services\XMLAgency;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode;
use DOMXPath;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XMLAgencyService
{
    public function __construct()
    {
        // No longer storing a single base URL - we'll determine it dynamically
    }

    /**
     * @throws Exception
     */
    public function sendRequest(array $requestData, string $soapAction): array
    {
        $xmlContent = $this->buildSoapXml($requestData);
        $endpoint = $this->getEndpointForAction($soapAction);
        return $this->makeRequest($endpoint, $xmlContent, $soapAction);
    }

    /**
     * @throws DOMException
     */
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

    /**
     * @throws DOMException
     */
    private function arrayToXml(array $data, DOMElement $parent, DOMDocument $dom): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $this->arrayToXml($value, $parent, $dom);
                } elseif ($key === '_flights') {
                    // Handle special flights structure
                    $this->createSearchFlights($value, $parent, $dom);
                } elseif ($key === 'PaxList') {
                    // Handle PaxList structure for booking
                    $this->createPaxList($value, $parent, $dom);
                } else {
                    $element = $this->createElement($key, $parent, $dom);
                    $this->arrayToXml($value, $element, $dom);
                }
            } else {
                $this->createTextElement($key, $value, $parent, $dom);
            }
        }
    }

    /**
     * @throws DOMException
     */
    private function createElement(string $name, DOMElement $parent, DOMDocument $dom): DOMElement
    {
        // Handle special XML Agency methods with proper namespaces
        if (in_array($name, ['AeroSearch', 'AeroPrebook', 'AeroBook', 'ConfirmBook', 'OrderInfo'])) {
            $element = $dom->createElementNS('http://tempuri.org/', $name);
            $parent->appendChild($element);
            return $element;
        }

        // Handle credentials block
        if ($name === 'credentials' || $name === 'authInfo') {
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

        // Handle pre booking params block
        if ($name === 'aeroPrebookParams') {
            $element = $dom->createElement($name);
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:a', 'http://schemas.datacontract.org/2004/07/SiteCity.Avia.Prebook');
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:i', 'http://www.w3.org/2001/XMLSchema-instance');
            $parent->appendChild($element);
            return $element;
        }

        // Handle booking params block
        if ($name === 'aeroBookParams') {
            $element = $dom->createElement($name);
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:a', 'http://schemas.datacontract.org/2004/07/SiteCity.Avia.Booking');
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:i', 'http://www.w3.org/2001/XMLSchema-instance');
            $parent->appendChild($element);
            return $element;
        }

        // Handle confirm booking params block
        if ($name === 'confirmParams') {
            $element = $dom->createElement($name);
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:a', 'http://schemas.datacontract.org/2004/07/SiteCity.BookInfo.ConfirmBook');
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:i', 'http://www.w3.org/2001/XMLSchema-instance');
            $parent->appendChild($element);
            return $element;
        }

        // Handle order info params block
        if ($name === 'orderInfoParams') {
            $element = $dom->createElement($name);
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:a', 'http://schemas.datacontract.org/2004/07/SiteCity.BookInfo.OrderInfo');
            $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:i', 'http://www.w3.org/2001/XMLSchema-instance');
            $parent->appendChild($element);
            return $element;
        }

        // Determine the correct prefix based on parent
        $prefix = $this->getElementPrefix($name, $parent);

        if ($prefix) {
            $element = $dom->createElement($prefix . ':' . $name);

            if ($name === 'SelectedTariffs') {
                $element->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:b', 'http://schemas.microsoft.com/2003/10/Serialization/Arrays');
            }
            $parent->appendChild($element);
            return $element;
        }

        // Default case
        $element = $dom->createElement($name);
        $parent->appendChild($element);
        return $element;
    }

    /**
     * @throws DOMException
     */
    private function createTextElement(string $name, $value, DOMElement $parent, DOMDocument $dom): void
    {
        $element = $this->createElement($name, $parent, $dom);
        if ($value === null) {
            $element->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'i:nil', 'true');
        } else {
            $element->textContent = $value;
        }
    }

    private function getElementPrefix(string $name, DOMElement $parent): ?string
    {
        // Get parent name without namespace prefix for easier comparison
        $parentName = $parent->localName ?? $parent->nodeName;

        if ($parentName === 'credentials' || $parentName === 'authInfo') {
            if (in_array($name, ['ApiLogin', 'ApiPassword', 'AuthExtendedData', 'Currency', 'DeviceId', 'Language', 'TokenGuid'])) {
                return 'a';
            }
        }

        if ($parentName === 'aeroSearchParams') {
            if (in_array($name, ['Adults', 'Childs', 'ExtendedParams', 'FlightClass', 'Infants', 'PartnerName', 'SearchFlights'])) {
                return 'a';
            }
        }

        if ($parentName === 'aeroPrebookParams' || $parentName === 'aeroBookParams') {
//            dump($name);
            if (in_array($name, ['ClientReference', 'CustomerFIO', 'Email', 'ExtendedParams', 'Marker', 'OfferCode', 'Partner', 'PaxList', 'Phone', 'SearchGuid', 'SelectedServices', 'SelectedTariffs', 'Utm'])) {
                return 'a';
            }
        }

        if ($parentName === 'confirmParams' || $parentName === 'orderInfoParams') {
            if (in_array($name, ['BookGuid', 'BookId', 'Price'])) {
                return 'a';
            }
        }

        if ($parentName === 'SelectedTariffs' || $parentName === 'a:SelectedTariffs') {
            return 'b';
        }

        if ($parentName === 'PaxList' || $parentName === 'a:PaxList') {
            if ($name === 'PaxData') {
                return 'b';
            }
        }

        // Elements within b:PaxData should use 'b:' prefix
        if ($parentName === 'PaxData' || $parentName === 'b:PaxData') {
            if (in_array($name, ['AgeType', 'BirthDay', 'BirthISO', 'Document', 'DocumentExDate', 'GenderType', 'MiddleName', 'Name', 'Surname', 'BonusCard'])) {
                return 'b';
            }
        }

        if ($parentName === 'SearchFlights' || $parentName === 'a:SearchFlights') {
            if ($name === 'SearchFlight') {
                return 'a';
            }
        }

        if ($parentName === 'SearchFlight' || $parentName === 'a:SearchFlight') {
            if (in_array($name, ['Date', 'IATAFrom', 'IATATo'])) {
                return 'a';
            }
        }

        return null;
    }

    /**
     * @throws DOMException
     */
    private function createSearchFlights(array $flightData, DOMElement $parent, DOMDocument $dom): void
    {
        // Create SearchFlights element
        $searchFlightsElement = $this->createElement('SearchFlights', $parent, $dom);

        // Create SearchFlight element for departure
        if (isset($flightData['departure'])) {
            $searchFlightElement = $this->createElement('SearchFlight', $searchFlightsElement, $dom);
            $this->arrayToXml($flightData['departure'], $searchFlightElement, $dom);
        }

        // Create SearchFlight element for return (if exists)
        if (isset($flightData['return'])) {
            $searchFlightElement = $this->createElement('SearchFlight', $searchFlightsElement, $dom);
            $this->arrayToXml($flightData['return'], $searchFlightElement, $dom);
        }
    }

    /**
     * @throws DOMException
     */
    private function createPaxList(array $paxData, DOMElement $parent, DOMDocument $dom): void
    {
        // Create PaxList element with proper namespace
        $paxListElement = $this->createElement('PaxList', $parent, $dom);
        $paxListElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:b', 'http://schemas.datacontract.org/2004/07/SiteCity.Common');

        // Create PaxData elements
        if (isset($paxData['PaxData'])) {
            foreach ($paxData['PaxData'] as $passenger) {
                $paxDataElement = $this->createElement('PaxData', $paxListElement, $dom);
                $this->arrayToXml($passenger, $paxDataElement, $dom);
            }
        }
    }

    /**
     * Get the appropriate endpoint URL based on the SOAP action
     *
     * As per XML Agency requirements:
     * - Search operations (AeroSearch) use: http://search-api.xml.agency/SiteCity
     * - All other operations (AeroBook, AeroPrebook, ConfirmBook, OrderInfo) use: http://api.city.travel/SiteCity
     */
    private function getEndpointForAction(string $soapAction): string
    {
        if ($soapAction === 'AeroSearch') {
            $baseUrl = config('xmlagency.search_url');
        } else {
            $baseUrl = config('xmlagency.main_url');
        }

        return $baseUrl . config('xmlagency.endpoint');
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    private function makeRequest(string $endpoint, string $xmlContent, string $soapAction): array
    {
        $this->logRequest($endpoint, $xmlContent);

        $actionUrl = config('xmlagency.soap_actions.' . $soapAction);

        if (!$actionUrl) {
            throw new Exception("Unknown SOAP action: {$soapAction}");
        }

//        Log::info('XML Agency Request', ['xml' => $xmlContent, 'action' => $soapAction, 'action_url' => $actionUrl]);

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

//        Log::info('XML Agency Response', ['body' => $response->body()]);

        $this->logResponse($endpoint, $response->body());
        if ($response->successful()) {
            return $this->parseXmlResponse($response->body());
        }

        throw new Exception('XML Agency request failed: ' . $response->body());
    }

    /**
     * Parse XML response and convert to array, handling namespaces properly
     */
    private function parseXmlResponse(string $xmlString): array
    {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($xmlString);

            $xpath = new DOMXPath($dom);

            // Register namespaces
            $xpath->registerNamespace('s', 'http://www.w3.org/2003/05/soap-envelope');
            $xpath->registerNamespace('temp', 'http://tempuri.org/');
            $xpath->registerNamespace('a', 'http://schemas.datacontract.org/2004/07/SiteCity.Avia.Search');
            $xpath->registerNamespace('common', 'http://schemas.datacontract.org/2004/07/SiteCity.Common');
            $xpath->registerNamespace('b', 'http://schemas.datacontract.org/2004/07/SiteCity.Common');

            // Get the main result element - dynamically find any Result element in temp namespace
            $resultNodes = $xpath->query("//*[namespace-uri()='http://tempuri.org/' and contains(local-name(), 'Result')]");

            if ($resultNodes->length === 0) {
                throw new Exception('Could not find any Result element in temp namespace in response');
            }

            $resultNode = $resultNodes->item(0);

            return $this->domNodeToArray($resultNode, $xpath);

        } catch (Exception $e) {
            Log::error('Failed to parse XML response', ['error' => $e->getMessage(), 'xml' => $xmlString]);
            throw new Exception('Failed to parse XML response: ' . $e->getMessage());
        }
    }

    /**
     * Convert DOM node to array recursively
     */
    private function domNodeToArray(DOMNode $node, DOMXPath $xpath): array
    {
        $result = [];

        // Handle text content
        if ($node->nodeType === XML_TEXT_NODE) {
            $textValue = trim($node->nodeValue);
            return empty($textValue) ? [] : ['text' => $textValue];
        }

        // Handle element nodes
        if ($node->nodeType === XML_ELEMENT_NODE) {
            // Get attributes
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    $result['@' . $attr->nodeName] = $attr->nodeValue;
                }
            }

            // Get child nodes
            $children = [];
            $hasElementChildren = false;

            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_TEXT_NODE) {
                    $textContent = trim($child->nodeValue);
                    if (!empty($textContent) && !$hasElementChildren) {
                        $result['value'] = $textContent;
                    }
                } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                    $hasElementChildren = true;
                    $childName = $child->localName ?: $child->nodeName;
                    $childValue = $this->domNodeToArray($child, $xpath);

                    // Handle multiple elements with same name
                    if (isset($children[$childName])) {
                        if (!is_array($children[$childName]) || !array_key_exists(0, $children[$childName])) {
                            $children[$childName] = [$children[$childName]];
                        }
                        $children[$childName][] = $childValue;
                    } else {
                        $children[$childName] = $childValue;
                    }
                }
            }

            // If we found element children, remove any text value as it's probably whitespace
            if ($hasElementChildren) {
                unset($result['value']);
                $result = array_merge($result, $children);
            }
        }

        return $result;
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

        Log::channel('xmlagency')->info("Request to {$endpoint}");
        Log::channel('xmlagency')->info($formattedRequestXml);
    }

    private function logResponse(string $endpoint, string $responseBody): void
    {
        try {
            // Try to parse and format the XML response
            $xml = new \SimpleXMLElement($responseBody);
            $dom = dom_import_simplexml($xml)->ownerDocument;
            $dom->formatOutput = true;
            $formattedXml = $dom->saveXML();

            Log::channel('xmlagency')->info("Response from {$endpoint}");
            Log::channel('xmlagency')->info($formattedXml);
        } catch (\Exception $e) {
            // If response is not valid XML, log it as is
            Log::channel('xmlagency')->info("Response from {$endpoint}");
            Log::channel('xmlagency')->info($responseBody);
        }
    }
}
