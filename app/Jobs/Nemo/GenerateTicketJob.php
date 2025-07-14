<?php

namespace App\Jobs\Nemo;

use App;
use App\Enum\FlightSupplier;
use App\Mail\XmlBookingTicketMail;
use App\Models\FlightBooking;
use App\Models\FlightTicket;
use App\Services\Nemo\RequestGenerate\TicketFlightRequestGenerateService;
use App\Services\Nemo\SoapService;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $maxExceptions = 1;

    private array $ticketData;

    public function __construct(protected FlightBooking $booking)
    {
        Log::info("DEBUG: GenerateTicketJob constructor called for booking {$this->booking->booking_reference}");
    }

    /**
     * Handle the job execution
     */
    public function handle(
        SoapService                        $soapService,
        TicketFlightRequestGenerateService $ticketFlightRequestGenerateService
    ): void
    {
        Log::info("DEBUG: GenerateTicketJob handle() called for booking {$this->booking->booking_reference}");

        if ($this->booking->flight_type == FlightSupplier::Nemo) {
            Log::info("Generate Nemo tickets for: {$this->booking->booking_reference} - Job Attempt: " . $this->attempts());

            // Check if tickets already exist to prevent duplicate generation
            if ($this->booking->tickets()->exists()) {
                Log::info("Tickets already exist for booking {$this->booking->booking_reference}, skipping generation");
                return;
            }

            try {
                $generatedRequest = $ticketFlightRequestGenerateService->generateTicketFlightRequest([
                    'book_id' => $this->booking->booking_reference,
                ]);

                $result = $soapService->callSoap($generatedRequest, 'Ticket_2_2');

                if (isset($result->Ticket_2_2Result->Errors)) {
                    $this->handleErrors($result);
                    return;
                }

                dd($result);
                // Extract data from XML response
                $orderInfo = $this->xmlBookingData['OrderInfoData'];
                $multiGatesInfo = $orderInfo['MultiGatesInfo'];

                // Extract passengers from PaxDataList (corrected structure based on logs)
                $passengersData = $orderInfo['PaxDataList']['PaxData'] ?? [];
                $passengers = isset($passengersData[0]) ? $passengersData : [$passengersData];

                // Extract contact information
                $contactData = [
                    'email' => $orderInfo['Email']['value'],
                    'phone' => $orderInfo['Phone']['value'],
                ];

                // Build flight data using XMLAgency logic
                $flightData = $this->buildFlightData($multiGatesInfo);

                Log::info("Processing passengers for XMLAgency booking: {$this->booking->booking_reference}");

                $tickets = array_map(function ($passenger) use ($flightData, $contactData) {
                    return $this->processPassenger($passenger, $flightData, $contactData);
                }, $passengers);

                Mail::to($contactData['email'])->send(new XmlBookingTicketMail($tickets, $this->ticketData));

                Log::info("GenerateTicketJob completed successfully for XMLAgency booking: {$this->booking->booking_reference}");

            } catch (Exception $e) {
                Log::error("XMLAgency GenerateTicketJob failed for: {$this->booking->booking_reference}. Error: " . $e->getMessage());
                Log::error("Stack trace: " . $e->getTraceAsString());
                throw $e; // Re-throw to trigger job retry
            }
        }
    }

    /**
     * Handle SOAP response errors.
     *
     * @param object $result
     */
    private function handleErrors(object $result): void
    {
        $errors = is_array($result->Ticket_2_2Result->Errors->Error)
            ? $result->Ticket_2_2Result->Errors->Error
            : [$result->Ticket_2_2Result->Errors->Error];

        foreach ($errors as $error) {
            $errorMessage = "Ответ от Nemo.API: Уровень - $error->Level, Код ошибки - $error->Code, Сообщение - $error->Message, Class - " . __CLASS__ . ", Function: " . __METHOD__;
            Log::channel('nemo')->error($errorMessage);
        }
    }

    /**
     * Build flight data from XMLAgency MultiGatesInfo
     */
    private function buildFlightData(array $multiGatesInfo): array
    {
        $offerInfo = $multiGatesInfo['OfferInfo'];
        $offerInfo = is_array($offerInfo) && isset($offerInfo[0]) ? $offerInfo : [$offerInfo];

        $allSegments = [];

        foreach ($offerInfo as $offer) {
            if (isset($offer['Segments']['OfferSegment'])) {
                $segments = $offer['Segments']['OfferSegment'];
                $segments = is_array($segments) && isset($segments[0]) ? $segments : [$segments];
                $allSegments = array_merge($allSegments, $segments);
            }
        }

        $segments = $allSegments;

        $outwardSegments = [];
        $returnSegments = [];

        foreach ($segments as $segment) {
            if ($segment['Rph']['value'] == '1') {
                $outwardSegments[] = $segment;
            } else if ($segment['Rph']['value'] == '2') {
                $returnSegments[] = $segment;
            }
        }

        $outwardData = null;
        $returnData = null;

        if (!empty($outwardSegments)) {
            $outwardData = $this->buildJourneyData($outwardSegments);
        }

        if (!empty($returnSegments)) {
            $returnData = $this->buildJourneyData($returnSegments);
        }

        return [
            'Outward' => $outwardData,
            'Return' => $returnData
        ];
    }

    /**
     * Build journey data from segments (adapted from XMLAgency FlightBookService)
     */
    private function buildJourneyData(array $segments): array
    {
        if (empty($segments)) {
            return [];
        }

        // Build segment data
        $segmentList = [];
        foreach ($segments as $segment) {
            $segmentList[] = [
                'FlightNum' => $segment['FlightNum']['value'],
                'FlightClass' => $segment['FlightClass']['value'],
                'FlightMinutes' => $segment['FlightMinutes']['value'],
                'FlightTime' => $segment['FlightTime']['value'],
                'OperatingAirlineName' => $segment['OperatingAirlineName']['value'],
                'Departure' => [
                    'City' => $segment['Departure']['City']['value'],
                    'Airport' => $segment['Departure']['Name']['value'],
                    'Code' => $segment['Departure']['Iata']['value'],
                    'Date' => $segment['Departure']['Date']['value']
                ],
                'Arrival' => [
                    'City' => $segment['Arrival']['City']['value'],
                    'Airport' => $segment['Arrival']['Name']['value'],
                    'Code' => $segment['Arrival']['Iata']['value'],
                    'Date' => $segment['Arrival']['Date']['value']
                ],
                'Baggage' => $segment['Baggage'] ?? null,
                'CabinBaggage' => $segment['CabinBaggage'] ?? null
            ];
        }

        return $segmentList;
    }

    /**
     * Process individual passenger and generate ticket
     */
    private function processPassenger(array $passenger, array $flightData, array $contactData): array
    {
        // Build full name from passenger data using correct XMLAgency structure
        $firstName = $passenger['Name']['value'];
        $lastName = $passenger['Surname']['value'];
        $fullName = trim($firstName . ' ' . $lastName);

        $ticketPath = 'tickets/' . Str::slug($fullName) . '__' . now()->getTimestamp() . '.pdf';

        // Prepare data for XML ticket template in the new format
        $ticketData = [
            'bookingReference' => $this->booking->booking_reference,
            'bookingDate' => $this->xmlBookingData['OrderInfoData']['Date']['value'],
            'fullName' => $fullName,
            'passportNumber' => $passenger['Document']['value'],
            'dateOfBirth' => $passenger['BirthDay']['value'],
            'contactEmail' => $contactData['email'],
            'contactPhone' => $contactData['phone'],
            'flightData' => $flightData,
        ];

        $this->ticketData = $ticketData;

        $pdf = SnappyPdf::loadView('pdf.xmlticket', $ticketData)
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


    /**
     * Handle job failure
     */
    public function failed(Throwable $exception)
    {
        Log::error("DEBUG: GenerateTicketJob failed for booking {$this->booking->booking_reference}");
        Log::error("Error: " . $exception->getMessage());
        Log::error("Stack trace: " . $exception->getTraceAsString());
    }
}
