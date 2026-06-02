<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Services\Geo\HotelNearbyService;
use Illuminate\Http\JsonResponse;

class HotelNearbyController extends Controller
{
    public function __construct(
        private readonly HotelNearbyService $nearbyService,
    ) {}

    /** Nearby hotel details.
     *
     * @localizationHeader
     *
     */
    public function show(string $hotel): JsonResponse
    {
        $hotelModel = $this->resolveHotel($hotel);

        if ($hotelModel === null) {
            return response()->json([
                'success' => false,
                'message' => 'Hotel not found.',
            ], 404);
        }

        if ($hotelModel->latitude === null || $hotelModel->longitude === null) {
            return response()->json([
                'success' => false,
                'message' => 'Hotel coordinates not available.',
            ], 422);
        }

        $data = $this->nearbyService->getNearbyForHotel($hotelModel);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function resolveHotel(string $hotel): ?Hotel
    {
        $hotel = trim($hotel);

        if ($hotel === '') {
            return null;
        }

        if (ctype_digit($hotel)) {
            return Hotel::where('hid', (int) $hotel)->first();
        }

        return Hotel::where('etg_id', $hotel)->first();
    }
}
