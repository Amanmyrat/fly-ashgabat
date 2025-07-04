<?php

namespace App\Jobs\TFusion;

use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Enum\PaymentType;
use App\Models\FlightBooking;
use App\Services\TravelFusion\Requests\CheckBookingRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckBookingStatusJob implements ShouldQueue, ShouldBeUnique
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
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'check-booking-' . $this->booking->booking_reference;
    }

    /**
     * @throws ConnectionException
     * @throws \Exception
     */
    public function handle(TravelFusionService $travelFusionService): void
    {
        if($this->booking->flight_type == FlightSupplier::TFUSION){
            Log::info("Checking booking status for: {$this->booking->booking_reference}. Try {$this->attempts()}");

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
    }

    private function handleSucceededStatus(): void
    {
        // Check if booking is already succeeded to prevent duplicate ticket generation
        if ($this->booking->status === BookingStatus::SUCCEEDED) {
            Log::info("Booking {$this->booking->booking_reference} already succeeded, skipping ticket generation");
            return;
        }

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

    /**
     * @throws \Exception
     */
    private function handleBookingInProgressStatus(): void
    {
        $this->booking->update(['status' => BookingStatus::BOOKING_IN_PROGRESS->value]);
        Log::info("Re-dispatching job for booking: {$this->booking->booking_reference}");

        if ($this->attempts() < $this->tries) {
            throw new \Exception('Booking still in progress');
        } else {
            Log::warning("Maximum attempts reached for booking: {$this->booking->booking_reference}");
        }
    }

    private function handleDuplicateStatus(): void
    {
        $this->booking->update(['status' => BookingStatus::DUPLICATE->value]);
        Log::warning("Duplicate booking detected for booking: {$this->booking->booking_reference}");
    }
}
