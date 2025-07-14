<?php

namespace App\Http\Controllers\Nemo;

use App\Http\Requests\Nemo\FlightProcessDetailsRequest;
use App\Services\GeoDataService;
use App\Services\Nemo\RequestGenerate\AdditionalOperationsRequestGenerateService;
use App\Services\Nemo\SoapService;
use Exception;
use Illuminate\Http\JsonResponse;
use Log;

class FlightProcessController
{
    public function __construct(
        protected SoapService                                $soapService,
        protected AdditionalOperationsRequestGenerateService $additionalOperationsRequestGenerateService,
        protected GeoDataService                             $geoDataService,
    )
    {
    }

    /**
     * Process details of flight
     *
     * @param FlightProcessDetailsRequest $request
     * @return JsonResponse
     */
    public function processDetails(FlightProcessDetailsRequest $request): JsonResponse
    {
        $operation = $request->input('operation');
        $flightId = $request->input('flight_id');

        $generatedRequest = $this->additionalOperationsRequestGenerateService->generateAdditionalOperationsRequest($request->all());
        $result = $this->soapService->callSoap($generatedRequest, 'AdditionalOperations_1_2');

        if (isset($result->AdditionalOperations_1_2Result->Errors)) {
            $errors = $result->AdditionalOperations_1_2Result->Errors->Error;
            $errors = is_array($errors) ? $errors : [$errors];
            foreach ($errors as $error) {
                Log::channel('nemo')->error(sprintf(
                    "Ответ от Nemo.API: Уровень - %s, Код ошибки - %s, Сообщение - %s, Номер перелета %s, Class - %s, Function: %s",
                    $error->Level, $error->Code, $error->Message, $request['flight_id'], __CLASS__, __METHOD__
                ));
            }

            return response()->json(['data' => $result], 400);
        }

        $finalResult = $this->processOperation($operation, $flightId, $result);

        return response()->json(['data' => $finalResult]);
    }

    /**
     * Log an informational message to a specific channel.
     *
     * @param string $message The message to log.
     * @param mixed|null $data Additional data to log.
     * @return void
     */
    private function logInfo(string $message, mixed $data = null): void
    {
        $logMessage = $message;

        if (!is_null($data)) {
            $dataString = is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $logMessage .= " | Additional Data: " . $dataString;
        }

        Log::channel('nemo')->info($logMessage);
    }

    /**
     * Processes the operation requested and returns the result based on the operation type and flight ID.
     *
     * @param string $operation The type of operation to perform.
     * @param string $flightId The flight ID for which the operation is performed.
     * @param object $result The result object from the SOAP call or similar service.
     * @return array The final operation result.
     */
    private function processOperation(string $operation, string $flightId, object $result): array
    {
        switch ($operation) {
            case 'GetFareFamilies':
                $this->logInfo("Получение семейства тарифов для перелета: $flightId");
                $this->logInfo('Ответ: ', (array)$result);

                $flightsByFareFamily = $result->AdditionalOperations_1_2Result->ResponseBody->FlightsByFareFamily;
                $tariffs = $this->transformFlightsToTariffs($flightsByFareFamily);

                return ['operation' => 'GFF', 'result' => $tariffs];

            case 'ActualizeFlight':
                $this->logInfo("Актуализация перелета для: $flightId");
                $this->logInfo('Ответ: ', (array)$result);
                $boolArray = $this->extractFlightStatuses($result);

                $isActualized = !in_array(false, $boolArray);

                $this->logInfo("Завершение актуализации перелета для: $flightId. Перелет " . ($isActualized ? 'актуален' : 'не актуален'));
                return ['operation' => 'AF', 'result' => $isActualized];

            default:
                throw new \InvalidArgumentException("Неизвестная операция: $operation");
        }
    }

    /**
     * Transform Nemo flights response to tariffs format
     *
     * @param object $flightsByFareFamily
     * @return array
     */
    private function transformFlightsToTariffs(object $flightsByFareFamily): array
    {
        $tariffs = [];
        $locale = app()->getLocale();

        $flights = $flightsByFareFamily->Flight;

        $flights = is_array($flights) ? $flights : [$flights];
        foreach ($flights as $flight) {
            // Skip flights without FareFamiliesDescription
            if (!isset($flight->FareFamiliesDescription) || !isset($flight->FareFamiliesDescription->Description)) {
                continue;
            }

            $tariff = [
                'id' => $flight->ID,
                'name' => $this->extractLocalizedFareName($flight->FareFamiliesDescription->Description, $locale),
                'price' => (float) $flight->PriceInfo->Price->PassengerFares->PassengerFare->TotalFare->Amount / 100, // Convert from kopecks to rubles
                'features' => []
            ];

            // Transform fare family parameters to features
            if (isset($flight->FareFamiliesDescription->Description->UniversalParameters->FareFamilyParameter)) {
                $parameters = is_array($flight->FareFamiliesDescription->Description->UniversalParameters->FareFamilyParameter) 
                    ? $flight->FareFamiliesDescription->Description->UniversalParameters->FareFamilyParameter 
                    : [$flight->FareFamiliesDescription->Description->UniversalParameters->FareFamilyParameter];

                foreach ($parameters as $parameter) {
                    $feature = $this->transformParameterToFeature($parameter, $locale);
                    if ($feature) {
                        $tariff['features'][] = $feature;
                    }
                }
            }

            $tariffs[] = $tariff;
        }

        return $tariffs;
    }

    /**
     * Extract localized fare name from description parameter with fallbacks
     *
     * @param object $description
     * @param string $locale
     * @return string
     */
    private function extractLocalizedFareName(object $description, string $locale): string
    {
        // First try to get localized name from description parameter
        if (isset($description->UniversalParameters->FareFamilyParameter)) {
            $parameters = is_array($description->UniversalParameters->FareFamilyParameter)
                ? $description->UniversalParameters->FareFamilyParameter
                : [$description->UniversalParameters->FareFamilyParameter];

            foreach ($parameters as $parameter) {
                if ($parameter->Code === 'description') {
                    $localizedName = $this->getLocalizedText($parameter->ShortDescription, $locale);
                    if (!empty($localizedName)) {
                        return $localizedName;
                    }

                    // If current locale not found, try to get any available language from description parameter
                    if (isset($parameter->ShortDescription->LangItem)) {
                        $langItems = is_array($parameter->ShortDescription->LangItem)
                            ? $parameter->ShortDescription->LangItem
                            : [$parameter->ShortDescription->LangItem];

                        foreach ($langItems as $langItem) {
                            if (!empty($langItem->Value)) {
                                return $langItem->Value;
                            }
                        }
                    }
                }
            }
        }

        // Fallback 1: Use top-level name if no localized description found
        if (!empty($description->Name)) {
            return $description->Name;
        }

        // Fallback 2: Use flight ID if everything else fails
        return 'Fare ' . ($description->ID ?? 'Unknown');
    }

    /**
     * Transform a fare family parameter to a feature
     *
     * @param object $parameter
     * @param string $locale
     * @return array|null
     */
    private function transformParameterToFeature(object $parameter, string $locale): ?array
    {
        // Skip description parameter as it's used for tariff name
        if ($parameter->Code === 'description') {
            return null;
        }

        $text = $this->getLocalizedText($parameter->ShortDescription, $locale);
        $fullDescription = $this->getLocalizedText($parameter->FullDescription, $locale);

        // Use full description if available, otherwise use short description
        $finalText = !empty($fullDescription) ? $fullDescription : $text;

        return [
            'text' => $finalText,
            'enabled' => $parameter->NeedToPay !== 'NotAvailable',
            'withCharge' => $parameter->NeedToPay === 'Charge'
        ];
    }

    /**
     * Get localized text from LangItem array
     *
     * @param object $description
     * @param string $locale
     * @return string
     */
    private function getLocalizedText(object $description, string $locale): string
    {
        if (!isset($description->LangItem)) {
            return '';
        }

        $langItems = is_array($description->LangItem) ? $description->LangItem : [$description->LangItem];

        // First try to find the requested locale
        foreach ($langItems as $langItem) {
            if (strtolower($langItem->Code) === strtolower($locale)) {
                return $langItem->Value;
            }
        }

        // Fallback to English
        foreach ($langItems as $langItem) {
            if (strtolower($langItem->Code) === 'en') {
                return $langItem->Value;
            }
        }

        // If no English found, return the first available
        return $langItems[0]->Value ?? '';
    }

    /**
     * Extracts the flight statuses from the result.
     *
     * @param object $result The result object from the SOAP call or similar service.
     * @return array An array of flight statuses.
     */
    private function extractFlightStatuses(object $result): array
    {
        $boolArray = [];
        if (isset($result->AdditionalOperations_1_2Result->ResponseBody->ActualizedFlight->Segments->Segment)) {
            $segments = $result->AdditionalOperations_1_2Result->ResponseBody->ActualizedFlight->Segments->Segment;
            $segments = is_array($segments) ? $segments : [$segments]; // Ensure $segments is always an array
            foreach ($segments as $segment) {
                $boolArray[] = $segment->SupplierInfo->Status == 'OK';
            }
        }
        return $boolArray;
    }

}

