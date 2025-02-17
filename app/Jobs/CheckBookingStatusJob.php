<?php

namespace App\Jobs;

use App\Enum\BookingStatus;
use App\Models\FlightBooking;
use App\Services\TravelFusion\Requests\CheckBookingRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckBookingStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected FlightBooking $booking;
    public int $tries = 20;
    public int $backoff = 5;

    public function __construct(FlightBooking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * @throws ConnectionException
     */
    public function handle(TravelFusionService $travelFusionService)
    {
        Log::info("Checking booking status for: {$this->booking->booking_reference}");

        $checkBookingRequest = (new CheckBookingRequestBuilder($this->booking->booking_reference))->build();
        $response = $travelFusionService->sendRequest($checkBookingRequest);

        $status = $response['CheckBooking']['Status'] ?? null;

        Log::info("Booking Reference: {$this->booking->booking_reference}, Status: {$status}");

        if ($status === 'Succeeded') {
            $this->booking->update(['status' => BookingStatus::APPROVED->value]);

            // Deduct balance if payment type is balance
            if ($this->booking->payment_type === 'balance' && $this->booking->user) {
                $amountToDeduct = $this->booking->price['Amount'];
                $this->booking->user->decrement('balance', $amountToDeduct);
            }

            GenerateTicketJob::dispatch($this->booking);
            return;
        }

        if ($status === 'Failed') {
            $this->booking->update(['status' => BookingStatus::FAILED->value]);
            return;
        }

        if ($status === 'Unconfirmed' || $status === 'UnconfirmedBySupplier') {
            $this->booking->update(['status' => BookingStatus::CANCELED->value]);
            return;
        }

        if ($status === 'BookingInProgress') {
            $this->booking->update(['status' => BookingStatus::IN_PROGRESS->value]);
            Log::info("Re-dispatching job for booking: {$this->booking->booking_reference}");

            // Re-dispatch the job with a delay
            self::dispatch($this->booking)->delay(now()->addSeconds(10));
        }
    }
}
