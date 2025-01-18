<?php

namespace App\Services;

use App\Services\TravelFusion\Requests\CheckRoutingRequestBuilder;
use App\Services\TravelFusion\Requests\StartRoutingRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use Illuminate\Http\Client\ConnectionException;

class FlightSearchService
{
    private TravelFusionService $travelFusionService;

    public function __construct(TravelFusionService $travelFusionService)
    {
        $this->travelFusionService = $travelFusionService;
    }

    /**
     * @throws ConnectionException
     */
    public function search(array $validatedData): array
    {
        $startRoutingRequest = (new StartRoutingRequestBuilder($validatedData))->build();
        $startRoutingResponse = $this->travelFusionService->sendRequest($startRoutingRequest);

        if (!isset($startRoutingResponse['StartRouting']['RouterList'])) {
            return ['message' => 'No result found'];
        }

        $routingId = $startRoutingResponse['StartRouting']['RoutingId'];
        $checkRoutingRequest = (new CheckRoutingRequestBuilder($routingId))->build();

        return $this->travelFusionService->sendRequest($checkRoutingRequest);
    }
}
