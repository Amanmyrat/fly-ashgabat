<?php

namespace App\Http\Controllers;

use App\Http\Resources\CityResource;
use App\Http\Resources\CharterFlightResource;
use App\Models\CharterFlight;
use App\Models\City;
use Illuminate\Http\JsonResponse;
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
     * Get distinct weekdays and times of flights for a specific route.
     *
     * @localizationHeader
     *
     * @param Request $request The HTTP request object containing departure_city_id and destination_city_id.
     * @return JsonResponse JSON response containing available flight schedules (weekdays and times).
     */
    public function getAvailableDates(Request $request): JsonResponse
    {
        $request->validate([
            'departure_city_id' => 'required|exists:cities,id',
            'destination_city_id' => 'required|exists:cities,id',
        ]);

        $schedules = CharterFlight::where('city_from_id', $request->departure_city_id)
            ->where('city_to_id', $request->destination_city_id)
            ->select('departure_weekday', 'departure_time')
            ->distinct()
            ->orderByRaw("FIELD(departure_weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
            ->orderBy('departure_time')
            ->get()
            ->map(function ($schedule) {
                return [
                    'weekday' => $schedule->departure_weekday,
                    'time' => $schedule->departure_time->format('H:i'),
                    'formatted' => $schedule->departure_weekday . ' at ' . $schedule->departure_time->format('H:i')
                ];
            });

        return response()->json($schedules);
    }

    /**
     * Get available flights for a specific route and weekday/time.
     *
     * @localizationHeader
     *
     * @param Request $request The HTTP request object containing departure_city_id, destination_city_id, and optionally weekday/time.
     * @return AnonymousResourceCollection Collection of available charter flights.
     */
    public function getAvailableFlights(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'departure_city_id' => 'required|exists:cities,id',
            'destination_city_id' => 'required|exists:cities,id',
            'weekday' => 'sometimes|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'time' => 'sometimes|date_format:H:i',
        ]);

        $query = CharterFlight::with(['cityFrom', 'cityTo'])
            ->where('city_from_id', $request->departure_city_id)
            ->where('city_to_id', $request->destination_city_id);

        // Filter by weekday if provided
        if ($request->has('weekday')) {
            $query->where('departure_weekday', $request->weekday);
        }

        // Filter by time if provided
        if ($request->has('time')) {
            $query->where('departure_time', $request->time);
        }

        $flights = $query->orderByRaw("FIELD(departure_weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
            ->orderBy('departure_time')
            ->get();

        return CharterFlightResource::collection($flights);
    }
}
