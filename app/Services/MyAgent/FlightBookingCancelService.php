<?php

namespace App\Services\MyAgent;

use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Models\FlightBooking;
use Exception;
use Illuminate\Support\Facades\Log;

class FlightBookingCancelService
{
    public function __construct(
        protected MyAgentService $myAgentService
    ) {
    }

    /**
     * @throws Exception
     */
    public function cancel(FlightBooking $booking): array
    {
        if ($booking->flight_type !== FlightSupplier::MYAGENT) {
            throw new Exception('This booking is not a MyAgent booking.');
        }

        if (!in_array($booking->status, [
            BookingStatus::PENDING,
            BookingStatus::BOOKING_IN_PROGRESS,
        ], true)) {
            throw new Exception('This booking cannot be cancelled.');
        }

        $response = $this->myAgentService->postForm('/avia/booking-cancel', [
            'billing' => $booking->booking_reference,
        ]);

        $booking->update([
            'status' => BookingStatus::CANCELLED->value,
        ]);

        Log::channel('myagent')->info('MyAgent booking cancelled ' . json_encode([
            'booking_reference' => $booking->booking_reference,
            'pid' => $response['pid'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'success' => true,
            'booking' => $booking->fresh(),
            'raw_meta' => [
                'pid' => $response['pid'] ?? null,
                'execution' => $response['time']['execution'] ?? null,
            ],
        ];
    }
}
