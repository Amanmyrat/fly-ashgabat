<?php

namespace App\Jobs;

use App\Enum\BookingStatus;
use App\Enum\PaymentType;
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
    public int $tries = 36;
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

        match ($status) {
            'Succeeded' => $this->handleSucceededStatus(),
            'Failed' => $this->handleFailedStatus(),
            'Unconfirmed' => $this->handleUnconfirmedStatus(),
            'UnconfirmedBySupplier' => $this->handleUnconfirmedBySupplierStatus(),
            'BookingInProgress' => $this->handleBookingInProgressStatus(),
            'Duplicate' => $this->handleDuplicateStatus(),
            default => Log::warning("Unknown status received: {$status} for booking: {$this->booking->booking_reference}")
        };
    }

    private function handleSucceededStatus(): void
    {
        $this->booking->update(['status' => BookingStatus::SUCCEEDED->value]);

        if ($this->booking->payment_type === PaymentType::BALANCE && $this->booking->user) {
            $amountToDeduct = $this->booking->price['Amount'];
            $this->booking->user->decrement('balance', $amountToDeduct);
        }

        GenerateTicketJob::dispatch($this->booking);
    }

    private function handleFailedStatus(): void
    {
        $this->booking->update(['status' => BookingStatus::FAILED->value]);
    }

    private function handleUnconfirmedStatus(): void
    {
        $this->booking->update(['status' => BookingStatus::UNCONFIRMED->value]);
    }

    private function handleUnconfirmedBySupplierStatus(): void
    {
        $this->booking->update(['status' => BookingStatus::UNCONFIRMED_BY_SUPPLIER->value]);
    }

    private function handleBookingInProgressStatus(): void
    {
        $this->booking->update(['status' => BookingStatus::BOOKING_IN_PROGRESS->value]);
        Log::info("Re-dispatching job for booking: {$this->booking->booking_reference}");

        self::dispatch($this->booking)->delay(now()->addSeconds(5));
    }

    private function handleDuplicateStatus(): void
    {
        $this->booking->update(['status' => BookingStatus::DUPLICATE->value]);
        Log::warning("Duplicate booking detected for booking: {$this->booking->booking_reference}");
    }
}
