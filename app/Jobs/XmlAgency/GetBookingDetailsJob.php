<?php

namespace App\Jobs\XmlAgency;

use App\Enum\BookingStatus;
use App\Enum\PaymentType;
use App\Models\FlightBooking;
use App\Services\XMLAgency\RequestBuilder\OrderInfoRequestBuilder;
use App\Services\XMLAgency\XMLAgencyService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GetBookingDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;
    public int $backoff = 60;
    public function __construct(protected FlightBooking $booking)
    {
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function handle(XMLAgencyService $xmlAgencyService): void
    {
        $orderInfoRequest = (new OrderInfoRequestBuilder($this->booking))->build();
        $orderInfoResponse = $xmlAgencyService->sendRequest($orderInfoRequest, 'OrderInfo');

        if ($orderInfoResponse['Success']['value'] != "true") {
            $errorMessage = $aeroBookResponse['OrderInfoResult']['ErrorString'] ?? 'Get booking details failed';

            \Log::info($errorMessage);
            return;
        }

        $status = $orderInfoResponse['OrderInfoData']['BookingStatus']['value'];

        Log::warning("Booking Reference: {$this->booking->booking_reference}, Status: {$status}");

        match ($status) {
            'Booked' => $this->handleSucceededStatus($orderInfoResponse),
            'Cancelled' => $this->handleFailedStatus(),
            'OnBooking', 'OnCancelling', 'WaitToBooking' => $this->handleBookingInProgressStatus(),
            default => Log::channel('xmlagency')->warning("Unknown status received: {$status} for booking: {$this->booking->booking_reference}")
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
     * @throws Exception
     */
    private function handleBookingInProgressStatus(): void
    {
        if ($this->attempts() < $this->tries) {
            throw new Exception('Booking still in progress');
        } else {
            Log::warning("Maximum attempts reached for booking: {$this->booking->booking_reference}");
        }

    }
}
