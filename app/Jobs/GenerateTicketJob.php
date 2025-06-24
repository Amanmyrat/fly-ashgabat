<?php

namespace App\Jobs;

use App\Mail\BookingTicketMail;
use App\Models\FlightBooking;
use App\Models\FlightTicket;
use App\Services\TravelFusion\Requests\GetBookingDetailsRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateTicketJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected FlightBooking $booking;
    public $tries = 1;
    public $maxExceptions = 1;

    public function __construct(FlightBooking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'generate-ticket-' . $this->booking->booking_reference;
    }

    /**
     * @throws ConnectionException
     */
    public function handle(TravelFusionService $travelFusionService)
    {
        Log::info("Generate tickets for: {$this->booking->booking_reference} - Job Attempt: " . $this->attempts());

        // Check if tickets already exist to prevent duplicate generation
        if ($this->booking->tickets()->exists()) {
            Log::info("Tickets already exist for booking {$this->booking->booking_reference}, skipping generation");
            return;
        }

        try {
            Log::info("Making GetBookingDetails request for: {$this->booking->booking_reference}");
            $response = $travelFusionService->sendRequest(
                (new GetBookingDetailsRequestBuilder($this->booking->booking_reference))->build()
            );

            if (!isset($response['GetBookingDetails'])) {
                Log::error("GetBookingDetails response missing for booking: {$this->booking->booking_reference}");
                return;
            }

            $bookingDetails = $response['GetBookingDetails'];
            Log::info("GetBookingDetails successful for: {$this->booking->booking_reference}");

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

            Log::info("Processing travelers for booking: {$this->booking->booking_reference}");
            // Process travelers and generate tickets
            $tickets = array_map(function ($traveler) use ($bookingData, $supplierReference, $contactData) {
                return $this->processTraveler($traveler, $bookingData, $supplierReference, $contactData);
            }, $travelers);

            Log::info("Sending email for booking: {$this->booking->booking_reference}");
            Mail::to($contactData['email'])->send(new BookingTicketMail($tickets, $bookingData));

            Log::info("GenerateTicketJob completed successfully for: {$this->booking->booking_reference}");

        } catch (\Exception $e) {
            Log::error("GenerateTicketJob failed for: {$this->booking->booking_reference}. Error: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e; // Re-throw to trigger job retry
        }
    }

    private function processTraveler(array $traveler, array $bookingData, string $supplierReference, array $contactData): array
    {
        $fullName = implode(' ', $traveler['Name']['NamePartList']['NamePart']);
        $ticketPath = 'tickets/' . Str::slug($fullName) . '__' . now()->getTimestamp() . '.pdf';

        $bookingModel = $this->booking;

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
