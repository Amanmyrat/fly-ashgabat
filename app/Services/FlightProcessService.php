<?php

namespace App\Services;

use App\Services\TravelFusion\Requests\ProcessDetailsRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use Exception;
use Illuminate\Http\Client\ConnectionException;

class FlightProcessService
{
    public function __construct(
        protected TravelFusionService $travelFusionService,
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

        $requiredParameters = $processDetailsResponse['ProcessDetails']['Router']['RequiredParameterList']['RequiredParameter'];

        $options = [];

        foreach ($requiredParameters as $param) {
            if ($param['Type'] === 'value_select' && !empty($param['DisplayText'])) {
                $name = $param['Name'];
                $options[$name] = [];

                // Extracting luggage options from DisplayText
                preg_match_all('/(\d+)\s*\((.*?)\)/', $param['DisplayText'], $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $options[$name][] = [
                        'key' => (int)$match[1],
                        'value' => $match[2],
                    ];
                }
            }
        }

        return [
            'success' => true,
            'options' => $options,
            'message' => 'Processing successful',
        ];

    }

}
