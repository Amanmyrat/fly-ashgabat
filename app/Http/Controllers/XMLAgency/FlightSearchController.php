<?php

namespace App\Http\Controllers\XMLAgency;

use App\Http\Controllers\Controller;
use App\Http\Requests\XMLAgencyFlightSearchRequest;
use App\Services\XMLAgency\FlightSearchService;
use Illuminate\Http\JsonResponse;

class FlightSearchController extends Controller
{
    public function __construct(
        private FlightSearchService $flightSearchService
    ) {}

    public function search(XMLAgencyFlightSearchRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $result = $this->flightSearchService->search($validatedData);

        return response()->json($result);
    }

    public function searchTest(): JsonResponse
    {
        $testData = [
            'departure_code' => 'ASB',
            'arrival_code' => 'IST',
            'departure_date' => '2024-06-15',
            'flight_type' => 'one-way',
            'adults_count' => 1,
            'children_count' => 0,
            'infants_count' => 0,
            'class_type' => 'economy'
        ];

        $result = $this->flightSearchService->search($testData);

        return response()->json($result);
    }
}
