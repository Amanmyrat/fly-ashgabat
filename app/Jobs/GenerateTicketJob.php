<?php

namespace App\Jobs;

use App\Mail\BookingTicketMail;
use App\Models\FlightBooking;
use App\Models\FlightTicket;
use App\Services\TravelFusion\Requests\GetBookingRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateTicketJob implements ShouldQueue
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
        Log::info("Generate tickets for: {$this->booking->booking_reference}");
        $response = $travelFusionService->sendRequest(
            (new GetBookingRequestBuilder($this->booking->booking_reference))->build()
        );

        if (!isset($response['GetBookingDetails'])) {
            return;
        }

        $bookingDetails = $response['GetBookingDetails'];

        $this->booking->update(['supplier_reference' => $bookingDetails['SupplierReference']]);
        $travelersList = $bookingDetails['BookingProfile']['TravellerList']['Traveller'] ?? [];

        $travelers = is_array($travelersList) && array_keys($travelersList) !== range(0, count($travelersList) - 1)
            ? [$travelersList]
            : $travelersList;

        unset($bookingDetails['RouterHistory']['BookingRouter']['RequiredParameterList']);
        $bookingData = $bookingDetails['RouterHistory']['BookingRouter'];
        $supplierReference = $bookingDetails['SupplierReference'];

        $contactDetails = $bookingDetails['BookingProfile']['ContactDetails'];
        $contactData = [
            'email' => $contactDetails['Email'],
            'phone' => $contactDetails['MobilePhone']['InternationalCode'] . $contactDetails['MobilePhone']['Number'],
        ];

        // Process travelers and generate tickets
        $tickets = array_map(function ($traveler) use ($bookingData, $supplierReference, $contactData) {
            return $this->processTraveler($traveler, $bookingData, $supplierReference, $contactData);
        }, $travelers);

        Mail::to($contactData['email'])->send(new BookingTicketMail($tickets, $bookingData));
    }

    private function processTraveler(array $traveler, array $bookingData, string $supplierReference, array $contactData): array
    {
        $fullName = implode(' ', $traveler['Name']['NamePartList']['NamePart']);
        $ticketPath = 'tickets/' . Str::slug($fullName) . '__' . now()->getTimestamp() . '.pdf';

        $bookingModel = $this->booking;
        Log::info($bookingModel->outward['Features']);
        $data = compact('traveler', 'bookingData', 'supplierReference', 'contactData', 'bookingModel');
        $pdf = SnappyPdf::loadView('pdf.ticket', $data)
            ->setOption('encoding', 'UTF-8');

        Storage::disk('public')->put($ticketPath, $pdf->download()->getOriginalContent());
        $ticketUrl = Storage::disk('public')->url($ticketPath);

        FlightTicket::create([
            'booking_id' => $this->booking->id,
            'name' => $fullName,
            'ticket_url' => $ticketUrl
        ]);

        return [
            'name' => $fullName,
            'ticket_url' => $ticketUrl
        ];
    }
}
