<?php

namespace App\Http\Controllers;

use App\Services\AirportLocatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoDataController extends Controller
{
    public function __construct(
        protected AirportLocatorService       $airportLocatorService,
    )
    {
    }

    /**
     * Get a list of airports
     *
     * @param Request $request The HTTP request object containing the search query.
     * @return JsonResponse JSON response containing airport search results.
     */
    public function getAirports(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string'
        ]);

        $result = $this->airportLocatorService->searchAirports($request['query']);

        return response()->json([
            'data' => $result
        ]);
    }
}
