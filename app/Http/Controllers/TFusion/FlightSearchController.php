<?php

namespace App\Http\Controllers\TFusion;

use App\Http\Controllers\BaseController;
use App\Http\Requests\FlightSearchRequest;
use App\Services\FlightSearchService;
use Illuminate\Http\JsonResponse;

class FlightSearchController extends BaseController
{
    public function __construct(protected FlightSearchService $flightSearchService)
    {
    }

    /**
     * Search tfusion flights
     *
     * @localizationHeader
     *
     * @param FlightSearchRequest $request
     * @return JsonResponse
     */
    public function search(FlightSearchRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        return $this->handleServiceCall(fn() => $this->flightSearchService->search($validatedData));
    }
}
