<?php

namespace App\Http\Controllers\TFusion;

use App\Http\Controllers\BaseController;
use App\Http\Requests\FlightProcessDetailsRequest;
use App\Services\TravelFusion\FlightProcessService;
use Illuminate\Http\JsonResponse;

class FlightProcessController extends BaseController
{
    public function __construct(protected FlightProcessService $flightProcessService)
    {
    }

    /**
     * Process a flight booking
     *
     * @param FlightProcessDetailsRequest $request
     * @return JsonResponse
     */
    public function processDetails(FlightProcessDetailsRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        return $this->handleServiceCall(fn() => $this->flightProcessService->processDetails($validatedData));
    }
}
