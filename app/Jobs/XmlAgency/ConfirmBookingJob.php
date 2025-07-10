<?php

namespace App\Jobs\XmlAgency;

use App\Enum\BookingStatus;
use App\Enum\PaymentType;
use App\Models\FlightBooking;
use App\Services\XMLAgency\RequestBuilder\ConfirmBookRequestBuilder;
use App\Services\XMLAgency\XMLAgencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConfirmBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected FlightBooking $booking)
    {
    }

    /**
     * @throws ConnectionException
     * @throws \Exception
     */
    public function handle(XMLAgencyService $xmlAgencyService): void
    {
        $confirmBookRequest = (new ConfirmBookRequestBuilder($this->booking))->build();
        $confirmBookResponse = $xmlAgencyService->sendRequest($confirmBookRequest, 'ConfirmBook');

        if ($confirmBookResponse['Success']['value'] != "true") {
            $errorMessage = $aeroBookResponse['ConfirmBookResult']['ErrorString'] ?? 'Confirm failed';

            \Log::info($errorMessage);

            GetBookingDetailsJob::dispatch($this->booking);
            return;
        }
        $status = $confirmBookResponse['OrderInfoData']['BookingStatus']['value'];

        Log::info("Booking Reference: {$this->booking->booking_reference}, Status: {$status}");

        match ($status) {
            'Booked' => $this->handleSucceededStatus($confirmBookResponse),
            'Cancelled' => $this->handleFailedStatus(),
            'WaitToBooking' => $this->handleBookingInProgressStatus(),
            default => Log::warning("Unknown status received: {$status} for booking: {$this->booking->booking_reference}")
        };
    }

    private function handleSucceededStatus(array $bookingInfo): void
    {
        $this->booking->update(['status' => BookingStatus::SUCCEEDED->value]);

        if ($this->booking->payment_type == PaymentType::BALANCE && $this->booking->user) {
            $amountToDeduct = $this->booking->price['Amount'];
            $this->booking->user->decrement('balance', $amountToDeduct);
        }

        GenerateTicketJob::dispatch($this->booking, $bookingInfo);
    }

    private function handleFailedStatus(): void
    {
        $this->booking->update(['status' => BookingStatus::FAILED->value]);
    }

    /**
     * @throws \Exception
     */
    private function handleBookingInProgressStatus(): void
    {
        $this->booking->update(['status' => BookingStatus::BOOKING_IN_PROGRESS->value]);
        Log::info("Re-dispatching job for booking: {$this->booking->booking_reference}");

        GetBookingDetailsJob::dispatch($this->booking);
    }
}
