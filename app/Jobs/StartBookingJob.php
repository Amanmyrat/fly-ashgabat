<?php

namespace App\Jobs;

use App\Models\FlightBooking;
use App\Services\TravelFusion\Requests\StartBookingRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StartBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected FlightBooking $booking;

    public function __construct(FlightBooking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * @throws ConnectionException
     */
    public function handle(TravelFusionService $travelFusionService)
    {
        $startBookingRequest = (new StartBookingRequestBuilder([
            'tf_booking_reference' => $this->booking->booking_reference,
            'price' => $this->booking->price
        ]))->build();

        $travelFusionService->sendRequest($startBookingRequest);
    }
}
