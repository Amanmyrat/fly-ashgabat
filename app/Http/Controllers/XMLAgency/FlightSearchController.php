<?php

namespace App\Http\Controllers\XMLAgency;

use App\Http\Controllers\Controller;
use App\Http\Requests\XMLAgency\FlightSearchRequest;
use App\Services\XMLAgency\FlightSearchService;
use Illuminate\Http\JsonResponse;

class FlightSearchController extends Controller
{
    public function __construct(
        private readonly FlightSearchService $flightSearchService
    ) {}

    /**
     * Search xml agency flights
     *
     * @localizationHeader
     *
     * @param FlightSearchRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function search(FlightSearchRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $result = $this->flightSearchService->search($validatedData);

        return response()->json(['data' => $result]);
    }
}
