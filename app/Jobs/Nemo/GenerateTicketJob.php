<?php

namespace App\Jobs\Nemo;

use App;
use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Mail\XmlBookingTicketMail;
use App\Models\FlightBooking;
use App\Models\FlightTicket;
use App\Services\GeoDataService;
use App\Services\Nemo\RequestGenerate\TicketFlightRequestGenerateService;
use App\Services\Nemo\SoapService;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Cache;
use Carbon\Carbon;
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
    private ?object $responseBody = null;

    public function __construct(protected FlightBooking $booking)
    {
        Log::info("DEBUG: GenerateTicketJob constructor called for booking {$this->booking->booking_reference}");
    }

    /**
     * Handle the job execution
     */
    public function handle(
        SoapService                        $soapService,
        TicketFlightRequestGenerateService $ticketFlightRequestGenerateService,
        GeoDataService                     $geoDataService
    ): void
    {
        Log::info("DEBUG: GenerateTicketJob handle() called for booking {$this->booking->booking_reference}");

        if ($this->booking->flight_type == FlightSupplier::NEMO) {
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

//                $result = Cache::remember('flights_ticket_' . $this->booking->booking_reference, 60 * 55, function () use ($soapService, $generatedRequest) {
//                    return $soapService->callSoap($generatedRequest, 'Ticket_2_2');
//                });

                $result = $soapService->callSoap($generatedRequest, 'Ticket_2_2');

                if (isset($result->Ticket_2_2Result->Errors)) {
                    $this->booking->update(['status' => BookingStatus::FAILED->value]);
                    $this->handleErrors($result);
                    return;
                }

                $responseBody = $result->Ticket_2_2Result->ResponseBody;

                $this->responseBody = $responseBody;

                $travellers = $responseBody->Travellers->Traveller;

                if (!is_array($travellers)) {
                    $travellers = [$travellers];
                }

                $service = $responseBody->Services->Service;
                $flightSegments = $service->Segments->FlightSegment;

                if (!is_array($flightSegments)) {
                    $flightSegments = [$flightSegments];
                }

                $contactData = $this->extractContactInfo($responseBody->DataItems->DataItem);

                $flightData = $this->buildNemoFlightData($flightSegments, $service, $geoDataService);

                Log::info("Processing passengers for Nemo booking: {$this->booking->booking_reference}");

                $tickets = [];
                foreach ($travellers as $traveller) {
                    $ticket = $this->processNemoPassenger($traveller, $flightData, $contactData, $responseBody);
                    $tickets[] = $ticket;
                }

                $this->booking->update(['status' => BookingStatus::SUCCEEDED->value]);

                Mail::to($contactData['email'])->send(new XmlBookingTicketMail($tickets, $this->ticketData));

                Log::info("GenerateTicketJob completed successfully for Nemo booking: {$this->booking->booking_reference}");

            } catch (Exception $e) {
                Log::error("Nemo GenerateTicketJob failed for: {$this->booking->booking_reference}. Error: " . $e->getMessage());
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
     * Extract contact information from DataItems
     */
    private function extractContactInfo(array $dataItems): array
    {
        $contactData = [
            'email' => '',
            'phone' => '',
        ];

        foreach ($dataItems as $dataItem) {
            if (isset($dataItem->Type) && $dataItem->Type === 'ContactInfo') {
                $contactInfo = $dataItem->ContactInfo;
                $contactData['email'] = $contactInfo->EmailID ?? '';
                $contactData['phone'] = $contactInfo->Telephone->PhoneNumber ?? '';
                break;
            }
        }

        return $contactData;
    }

    /**
     * Build flight data from Nemo flight segments
     */
    private function buildNemoFlightData(array $flightSegments, object $service, GeoDataService $geoDataService): array
    {
        $segmentList = [];

        foreach ($flightSegments as $segment) {

            $departureInfo = $geoDataService->getAirportInfo([
                'Type' => 'airport',
                'Code' => $segment->DepatureAirport->Code
            ]);


            $arrivalInfo = $geoDataService->getAirportInfo([
                'Type' => 'airport',
                'Code' => $segment->ArrivalAirport->Code
            ]);


            $departureDateTime = Carbon::createFromFormat('Y-m-d\TH:i:s', $segment->DepatureDateTime);
            $arrivalDateTime = Carbon::createFromFormat('Y-m-d\TH:i:s', $segment->ArrivalDateTime);

            $segmentList[] = [
                'FlightNum' => $segment->FlightNumber,
                'FlightClass' => $segment->BookingClassCode,
                'FlightMinutes' => $segment->FlightTime,
                'FlightTime' => $this->convertMinutesToHHMMSS($segment->FlightTime),
                'OperatingAirlineName' => $segment->OperatingAirline,
                'Departure' => [
                    'City' => $departureInfo['cityName'] ?: $segment->DepatureAirport->CityCode,
                    'Airport' => $departureInfo['airportName'] ?: $segment->DepatureAirport->Code,
                    'Code' => $segment->DepatureAirport->Code,
                    'Date' => $departureDateTime->format('d.m.Y H:i')
                ],
                'Arrival' => [
                    'City' => $arrivalInfo['cityName'] ?: $segment->ArrivalAirport->CityCode,
                    'Airport' => $arrivalInfo['airportName'] ?: $segment->ArrivalAirport->Code,
                    'Code' => $segment->ArrivalAirport->Code,
                    'Date' => $arrivalDateTime->format('d.m.Y H:i')
                ],
                'Baggage' => $this->extractBaggageForSegment($segment->ID),
                'CabinBaggage' => $this->extractCabinBaggageForSegment($segment->ID),
                'SegmentID' => $segment->ID,
                'RequestedSegment' => $segment->RequestedSegment ?? 0
            ];
        }

        return $this->separateSegmentsByDirection($segmentList, $service->DirectionType);
    }

    /**
     * Convert minutes to HH:MM:SS format
     */
    private function convertMinutesToHHMMSS(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%02d:%02d:00', $hours, $remainingMinutes);
    }

    /**
     * Separate segments into outward and return based on DirectionType
     */
    private function separateSegmentsByDirection(array $segmentList, string $directionType): array
    {
        switch ($directionType) {
            case 'RT':
            case 'SingleOJ':
            case 'DoubleOJ':
                $outwardSegments = [];
                $returnSegments = [];


                foreach ($segmentList as $segment) {
                    if ($segment['RequestedSegment'] === 0) {
                        $outwardSegments[] = $segment;
                    } else {
                        $returnSegments[] = $segment;
                    }
                }

                return [
                    'Outward' => $outwardSegments,
                    'Return' => !empty($returnSegments) ? $returnSegments : null
                ];

            default:

                return [
                    'Outward' => $segmentList,
                    'Return' => null
                ];
        }
    }

    /**
     * Extract baggage information for a specific segment
     */
    private function extractBaggageForSegment(int $segmentId): ?string
    {
        return $this->extractBaggageInfo($segmentId, 'baggage', 'CheckedBaggage');
    }

    /**
     * Extract cabin baggage information for a specific segment
     */
    private function extractCabinBaggageForSegment(int $segmentId): ?string
    {
        return $this->extractBaggageInfo($segmentId, 'carry_on', 'CabinBaggage');
    }

    /**
     * Universal baggage extraction method
     */
    private function extractBaggageInfo(int $segmentId, string $fareFamilyCode, string $baggageType): ?string
    {
        if (!isset($this->responseBody)) {
            return null;
        }

        $price = $this->responseBody->Price;

        if (isset($price->FareFamiliesDescriptions->Description->UniversalParameters->FareFamilyParameter)) {
            $parameters = $price->FareFamiliesDescriptions->Description->UniversalParameters->FareFamilyParameter;

            if (!is_array($parameters)) {
                $parameters = [$parameters];
            }

            foreach ($parameters as $parameter) {
                if (isset($parameter->Code) && $parameter->Code === $fareFamilyCode) {
                    if (isset($parameter->ShortDescription->LangItem)) {
                        $langItems = $parameter->ShortDescription->LangItem;
                        if (!is_array($langItems)) {
                            $langItems = [$langItems];
                        }

                        $ruText = '';
                        $enText = '';

                        // Extract both Russian and English descriptions
                        foreach ($langItems as $langItem) {
                            if ($langItem->Code === 'RU') {
                                $ruText = $langItem->Value;
                            } elseif ($langItem->Code === 'EN') {
                                $enText = $langItem->Value;
                            }
                        }


                        if ($ruText && $enText) {
                            return $ruText . ' / ' . $enText;
                        } elseif ($ruText) {
                            return $ruText;
                        } elseif ($enText) {
                            return $enText;
                        }

                        // Fallback to first available language
                        if (!empty($langItems)) {
                            return $langItems[0]->Value;
                        }
                    }
                }
            }
        }


        if (isset($price->PriceBreakdown->PricePart->PassengerTypePriceBreakdown->PassengerTypePrice->Tariffs->Tariff)) {
            $tariffs = $price->PriceBreakdown->PricePart->PassengerTypePriceBreakdown->PassengerTypePrice->Tariffs->Tariff;


            if (!is_array($tariffs)) {
                $tariffs = [$tariffs];
            }

            foreach ($tariffs as $tariff) {
                if (isset($tariff->SegmentID) && $tariff->SegmentID == $segmentId) {
                    if (isset($tariff->BaggageDetailsList->Baggage)) {
                        $baggage = $tariff->BaggageDetailsList->Baggage;


                        if ($baggageType === 'CheckedBaggage') {
                            if ($baggage->Weight && $baggage->Weight !== '0') {
                                return $baggage->Weight . ' ' . $baggage->WeightUnit;
                            }
                            if ($baggage->Count && $baggage->Count > 0) {
                                return $baggage->Count . ' piece(s)';
                            }
                        }

                        elseif ($baggageType === 'CabinBaggage') {
                            if ($baggage->Type === 'CabinBaggage' && $baggage->Weight && $baggage->Weight !== '0') {
                                return $baggage->Weight . ' ' . $baggage->WeightUnit;
                            }
                        }
                    }


                    if ($baggageType === 'CheckedBaggage' && isset($tariff->FreeBaggage->Value) && $tariff->FreeBaggage->Value !== '0') {
                        $value = $tariff->FreeBaggage->Value;
                        $measure = $tariff->FreeBaggage->Measure;
                        return $value . ' ' . $measure;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Process individual Nemo passenger and generate ticket
     */
    private function processNemoPassenger(object $traveller, array $flightData, array $contactData, object $responseBody): array
    {
        $firstName = $traveller->Name;
        $lastName = $traveller->LastName;
        $fullName = trim($firstName . ' ' . $lastName);

        $ticketPath = 'tickets/' . Str::slug($fullName) . '__' . now()->getTimestamp() . '.pdf';

        $passportNumber = $this->extractPassportNumber($responseBody->DataItems->DataItem, $traveller->ID);

        $bookingDate = Carbon::parse($responseBody->DateInfo->Created);

        $ticketData = [
            'bookingReference' => $this->booking->booking_reference,
            'bookingDate' => $bookingDate->format('d.m.Y H:i'),
            'fullName' => $fullName,
            'passportNumber' => $passportNumber,
            'dateOfBirth' => $traveller->DateOfBirth,
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
     * Extract passport number from DataItems for specific traveller
     */
    private function extractPassportNumber(array $dataItems, int $travellerId): string
    {
        foreach ($dataItems as $dataItem) {
            if (isset($dataItem->Type) && $dataItem->Type === 'IDDocument' &&
                isset($dataItem->TravellerRef) && $dataItem->TravellerRef->Ref == $travellerId) {
                return $dataItem->Document->Number ?? '';
            }
        }
        return '';
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
