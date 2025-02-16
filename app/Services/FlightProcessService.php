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

        // TODO here we should get required luggage options
        return [
            'success' => true,
            'message' => 'Processing successful',
        ];
    }

}
