<?php

namespace App\Http\Controllers;

use App\Http\Resources\CityResource;
use App\Http\Resources\CharterFlightResource;
use App\Models\CharterFlight;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharterFlightController extends Controller
{
    /**
     * Get all cities that have at least one flight departing.
     *
     * @localizationHeader
     *
     * @return AnonymousResourceCollection Collection of cities with departing flights.
     */
    public function getDepartureCities(): AnonymousResourceCollection
    {
        $cities = City::whereHas('charterFlightsFrom')
            ->select('id', 'name', 'code')
            ->get();

        return CityResource::collection($cities);
    }

    /**
     * Get unique destination cities available from a specific departure city.
     *
     * @localizationHeader
     *
     * @param Request $request The HTTP request object containing departure_city_id.
     * @return AnonymousResourceCollection Collection of destination cities.
     */
    public function getDestinationCities(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'departure_city_id' => 'required|exists:cities,id',
        ]);

        $cities = City::whereHas('charterFlightsTo', function ($query) use ($request) {
            $query->where('city_from_id', $request->departure_city_id);
        })
        ->select('id', 'name', 'code')
        ->get();

        return CityResource::collection($cities);
    }

    /**
     * Get distinct dates of flights for a specific route.
     *
     * @localizationHeader
     *
     * @param Request $request The HTTP request object containing departure_city_id and destination_city_id.
     * @return \Illuminate\Http\JsonResponse JSON response containing available flight dates.
     */
    public function getAvailableDates(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'departure_city_id' => 'required|exists:cities,id',
            'destination_city_id' => 'required|exists:cities,id',
        ]);

        $dates = CharterFlight::where('city_from_id', $request->departure_city_id)
            ->where('city_to_id', $request->destination_city_id)
            ->selectRaw('DATE(departure_datetime) as date')
            ->distinct()
            ->orderBy('date')
            ->pluck('date');

        return response()->json($dates);
    }

    /**
     * Get available flights for a specific route and date.
     *
     * @localizationHeader
     *
     * @param Request $request The HTTP request object containing departure_city_id, destination_city_id, and date.
     * @return AnonymousResourceCollection Collection of available charter flights.
     */
    public function getAvailableFlights(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'departure_city_id' => 'required|exists:cities,id',
            'destination_city_id' => 'required|exists:cities,id',
            'date' => 'required|date',
        ]);

        $flights = CharterFlight::with(['cityFrom', 'cityTo'])
            ->where('city_from_id', $request->departure_city_id)
            ->where('city_to_id', $request->destination_city_id)
            ->whereDate('departure_datetime', $request->date)
            ->orderBy('departure_datetime')
            ->get();

        return CharterFlightResource::collection($flights);
    }
} 