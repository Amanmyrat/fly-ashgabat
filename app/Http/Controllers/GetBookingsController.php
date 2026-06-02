<?php

namespace App\Http\Controllers;

use App\Services\FlightBookingFormatterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class GetBookingsController extends Controller
{
    public function __construct(
        protected FlightBookingFormatterService $flightBookingFormatter
    ) {
    }

    /**
     * Get bookings
     *
     * @localizationHeader
     *
     * @return JsonResponse
     */
    public function __invoke(): JsonResponse
    {
        $user = Auth::user();

        $flights = $user->flightBookings()
            ->with('tickets')
            ->latest()
            ->get()
            ->map(function ($booking) {
                $row = $this->flightBookingFormatter->formatBooking($booking);

                return array_merge($row, [
                    'type'       => 'flight',
                    'created_at' => $booking->created_at->toIso8601String(),
                ]);
            });

        $hotels = $user->hotelBookings()
            ->latest()
            ->get()
            ->map(fn ($h) => $h->toApiArray());

        $data = $flights->concat($hotels)->sortByDesc(fn (array $r) => $r['created_at'] ?? '')->values();

        return response()->json(['data' => $data]);
    }
} 