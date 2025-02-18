<?php

namespace App\Http\Controllers;

use App\Services\AirportLocatorService;
use App\Services\GeoDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class GeoDataController extends Controller
{
    public function __construct(
        protected AirportLocatorService $airportLocatorService,
        protected GeoDataService $geoDataService,
    )
    {
    }

    /**
     * Get a list of airports
     *
     * @localizationHeader
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

    /**
     * Get nationality information.
     *
     *
     * @localizationHeader
     *
     * @return JsonResponse JSON response containing information about the Nationalities.
     */
    public function getNationality(): JsonResponse
    {
        return response()->json([
            'data' => $this->geoDataService->getNationality()
        ]);
    }
}
