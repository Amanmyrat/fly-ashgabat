<?php

namespace App\Services\Nemo;

use Illuminate\Support\Facades\Log;

class SoapService
{
    public function callSoap($request, $requestTypeName)
    {
        try {
            $aviaWsdl = config('nemo.url');
            $client = new \SoapClient($aviaWsdl, array('trace' => 1));

            $result = $client->__soapCall($requestTypeName, $request);

            // Log the request and response after the call
            $this->logSoapRequest($client, $requestTypeName, $aviaWsdl);
            $this->logSoapResponse($client, $requestTypeName);

            Log::channel('nemo')->info("Nemo SOAP call '{$requestTypeName}' completed successfully");
            return $result;
        } catch (\SoapFault $exception) {
            Log::channel('nemo')->error("Nemo SOAP call '{$requestTypeName}' failed. Reason: " . $exception->getMessage());
            Log::critical("Class: " . __CLASS__ . ", Method: " . __METHOD__ . ", Operation: {$requestTypeName}, Message: " . $exception->getMessage());

            return response()->json([
                'data' => $exception->getMessage(),
                'error_type' => 'Nemo'
            ]);
        }
    }

    /**
     * Log the SOAP request XML with endpoint information
     */
    private function logSoapRequest(\SoapClient $client, string $requestTypeName, string $endpoint): void
    {
        try {
            $requestXml = $client->__getLastRequest();
            if ($requestXml) {
                // Format the XML for better readability
                $formattedXml = $this->formatXml($requestXml);
                Log::channel('nemo')->info("Nemo SOAP Request - Operation: '{$requestTypeName}' to {$endpoint}");
                Log::channel('nemo')->info($formattedXml);
            }
        } catch (\Exception $e) {
            Log::channel('nemo')->warning("Could not log SOAP request XML for '{$requestTypeName}': " . $e->getMessage());
        }
    }

    /**
     * Log the SOAP response XML
     */
    private function logSoapResponse(\SoapClient $client, string $requestTypeName): void
    {
        try {
            $responseXml = $client->__getLastResponse();
            if ($responseXml) {
                // Format the XML for better readability
                $formattedXml = $this->formatXml($responseXml);
                Log::channel('nemo')->info("Nemo SOAP Response XML for '{$requestTypeName}':");
                Log::channel('nemo')->info($formattedXml);
            }
        } catch (\Exception $e) {
            Log::channel('nemo')->warning("Could not log SOAP response XML for '{$requestTypeName}': " . $e->getMessage());
        }
    }

    /**
     * Format XML for better readability in logs
     */
    private function formatXml(string $xml): string
    {
        try {
            $dom = new \DOMDocument('1.0', 'utf-8');
            $dom->formatOutput = true;
            $dom->preserveWhiteSpace = false;
            $dom->loadXML($xml);

            $formattedXml = $dom->saveXML();

            // Clean up excessive newlines and spaces similar to XMLAgencyService
            $formattedXml = preg_replace('/\n\s*\n/', "\n", $formattedXml);
            return preg_replace('/\s{2,}/', ' ', $formattedXml);
        } catch (\Exception $e) {
            // If XML formatting fails, return the original XML
            return $xml;
        }
    }
}
