<?php

namespace App\Services\TravelFusion;

use App\Services\TravelFusion\Requests\ProcessDetailsRequestBuilder;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;

class FlightProcessService
{
    public function __construct(
        protected TravelFusionService   $travelFusionService,
        protected FlightFeaturesService $featuresService
    )
    {
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function processDetails(array $validatedData): array
    {
        $processDetailsRequest = (new ProcessDetailsRequestBuilder($validatedData))->build();
        $processDetailsResponse = $this->travelFusionService->sendRequest($processDetailsRequest);

        if (!isset($processDetailsResponse['ProcessDetails']['Router']['GroupList']['Group'])) {
            return [
                'success' => false,
                'message' => 'No result(ProcessDetails) found',
                'data' => $processDetailsResponse
            ];
        }

        $features = $processDetailsResponse['ProcessDetails']['Router']['Features'] ?? [];

        $outwardData = $processDetailsResponse['ProcessDetails']['Router']['GroupList']['Group']['OutwardList']['Outward'];
        $outwardCacheKey = $validatedData['routing_id'] . '_' . $validatedData['outward_id'];
        $this->setSegmentFeatures($outwardData, $features, $outwardCacheKey);

        if (isset($processDetailsResponse['ProcessDetails']['Router']['GroupList']['Group']['ReturnList'])) {
            $returnData = $processDetailsResponse['ProcessDetails']['Router']['GroupList']['Group']['ReturnList']['Return'];
            $returnCacheKey = $validatedData['routing_id'] . '_' . $validatedData['return_id'];
            $this->setSegmentFeatures($returnData, $features, $returnCacheKey);
        }

        $requiredParameters = $processDetailsResponse['ProcessDetails']['Router']['RequiredParameterList']['RequiredParameter'];

        $options = [];
        foreach ($requiredParameters as $param) {
            if ($param['Type'] === 'value_select' && !empty($param['DisplayText']) && $param['Name'] != 'FrequentFlyerType') {
                $name = $param['Name'];
                $perPassenger = $param['PerPassenger'] ?? false;
                $optionsList = [];

                // Extracting luggage options from DisplayText
                preg_match_all('/(\d+)\s*\((.*?)\)/', $param['DisplayText'], $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $optionsList[] = [
                        'key' => (int)$match[1],
                        'value' => $match[2],
                    ];
                }

                $options[] = [
                    'name' => $name,
                    'per_passenger' => $perPassenger === 'true' || $perPassenger === true,
                    'options' => $optionsList
                ];
            }
        }

        Cache::put('options_'.$validatedData['routing_id'], $options, now()->addMinutes(15));
        return [
            'success' => true,
            'options' => array_values(array_filter($options, fn($option) => !empty($option['options']))),
            'message' => 'Processing successful',
        ];

    }

    private function setSegmentFeatures($flightData, $features, $cacheKey)
    {
        $segments = $flightData['SegmentList']['Segment'] ?? [];
        $segments = isset($segments[0]) ? $segments : [$segments];

        $operator = $segments[0]['VendingOperator'] ?? $segments[0]['TfVendingOperator'];
        $supplierClass = $segments[0]['TravelClass']['SupplierClass'] ?? '';

        if (count($features)) {
            $relevantFeatures = $this->featuresService->getRelevantFeatures($features, $supplierClass, $operator['Code']);
        }

        $features = $relevantFeatures ?? [
                "HoldBag" => false,
                "SmallCabinBag" => false,
                "LargeCabinBag" => false,
                "FlightChange" => false,
                "Cancellation" => false
            ];
        Cache::put('process_'.$cacheKey, $features, now()->addMinutes(15));
    }

}
