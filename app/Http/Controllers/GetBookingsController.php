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
        $bookings = Auth::user()->flightBookings()
            ->with('tickets')
            ->latest()
            ->get()
            ->map(fn($booking) => $this->flightBookingFormatter->formatBooking($booking));

        return response()->json(['data' => $bookings]);
    }
} 