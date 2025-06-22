<?php

namespace App\Http\Controllers\XMLAgency;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlightSearchRequest;
use App\Http\Requests\XMLAgencyFlightSearchRequest;
use App\Services\XMLAgency\FlightSearchService;
use Illuminate\Http\JsonResponse;

class FlightSearchController extends Controller
{
    public function __construct(
        private FlightSearchService $flightSearchService
    ) {}

    /**
     * Search xml agency flights
     *
     * @localizationHeader
     *
     * @param XMLAgencyFlightSearchRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function search(XMLAgencyFlightSearchRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $result = $this->flightSearchService->search($validatedData);

        return response()->json($result);
    }
}
