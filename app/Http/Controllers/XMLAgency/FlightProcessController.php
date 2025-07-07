<?php

namespace App\Http\Controllers\XMLAgency;

use App\Http\Controllers\BaseController;
use App\Http\Requests\XMLAgency\FlightProcessDetailsRequest;
use App\Services\XMLAgency\FlightProcessService;
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

        return $this->handleServiceCall(fn() => $this->flightProcessService->processFlight($validatedData));
    }
}
