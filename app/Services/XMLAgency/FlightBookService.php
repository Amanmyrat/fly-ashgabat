<?php

namespace App\Services\XMLAgency;

use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Enum\PaymentType;
use App\Models\FlightBooking;
use App\Models\User;

use App\Http\Requests\XMLAgency\AeroBookRequestBuilder;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FlightBookService
{
    public function __construct(
        protected XMLAgencyService $xmlAgencyService
    ) {
    }

    /**
     * Process XMLAgency booking
     *
     * @throws ConnectionException
     * @throws Exception
     */
    public function processBooking(array $validatedData, ?User $user): array
    {
        // Build AeroBook request (no need for flight offer data)
        $aeroBookRequest = (new AeroBookRequestBuilder($validatedData))->build();

        // Send booking request to XMLAgency
        $aeroBookResponse = $this->xmlAgencyService->sendRequest($aeroBookRequest, 'AeroBook');

        if ($aeroBookResponse['ErrorCode']['value'] != "-1" || $aeroBookResponse['Success']['value'] != "true") {
            $errorMessage = $aeroBookResponse['AeroBookResult']['ErrorString'] ?? 'Booking failed';
            return [
                'success' => false,
                'message' => $errorMessage,
                'data' => $aeroBookResponse
            ];
        }

        $bookingResult = $aeroBookResponse;

        // Extract actual price from booking response
        $actualPrice = [
            'Amount' => $bookingResult['FullPrice']['value'],
            'Currency' => config('xmlagency.currency', 'EUR')
        ];

        // Extract flight data from booking response
        $offers = $bookingResult['Offers']['OfferInfo'] ?? [];
        $firstOffer = is_array($offers) && isset($offers[0]) ? $offers[0] : $offers;

        // Get all segments from the booking response
        $segments = $firstOffer['Segments']['OfferSegment'] ?? [];
        // Ensure segments is an array
        if (!is_array($segments) || !isset($segments[0])) {
            $segments = [$segments];
        }

        // Split segments into outward and return based on Rph
        $outwardSegments = [];
        $returnSegments = [];

        foreach ($segments as $segment) {
            if ($segment['Rph']['value'] == '1') {
                $outwardSegments[] = $segment;
            } else if ($segment['Rph']['value'] == '2') {
                $returnSegments[] = $segment;
            }
        }

        // Determine if it's round-trip based on the presence of return segments
        $isRoundTrip = !empty($returnSegments);

        // Calculate origin and destination from segments
        $origin = ['Code' => 'Unknown'];
        $destination = ['Code' => 'Unknown'];

        if (!empty($outwardSegments)) {
            $origin = [
                'Code' => $outwardSegments[0]['Departure']['Iata']['value'],
                'Name' => $outwardSegments[0]['Departure']['Name']['value'] ?? '',
                'City' => $outwardSegments[0]['Departure']['City']['value'] ?? ''
            ];
            $lastOutwardSegment = end($outwardSegments);
            $destination = [
                'Code' => $lastOutwardSegment['Arrival']['Iata']['value'],
                'Name' => $lastOutwardSegment['Arrival']['Name']['value'] ?? '',
                'City' => $lastOutwardSegment['Arrival']['City']['value'] ?? ''
            ];
        }

        // Build outward and return data using the same structure as FlightSearchService
        $outwardData = null;
        $returnData = null;

        if (!empty($outwardSegments)) {
            $outwardData = $this->buildJourneyData($outwardSegments);
        }

        if (!empty($returnSegments)) {
            $returnData = $this->buildJourneyData($returnSegments);
        }

        // Create booking record
        $bookingData = [
            'user_id' => $user?->id ?? null,
            'booking_reference' => $bookingResult['BookId']['value'], // Using BookId as booking_reference
            'supplier_reference' => $bookingResult['BookGuid']['value'], // Using BookGuid as supplier_reference
            'flight_type' => FlightSupplier::XMLAGENCY,
            'origin' => $origin,
            'destination' => $destination,
            'outward' => $outwardData,
            'return' => $returnData,
            'price' => $actualPrice,
            'payment_type' => $validatedData['payment_type'],
            'status' => BookingStatus::PENDING->value,
        ];

        $booking = FlightBooking::create($bookingData);

        if (!$booking) {
            throw new \Exception('Failed to create booking');
        }

        // Create contact details (XMLAgency only needs email and phone)
        if (!empty($validatedData['contact_details'])) {
            $contactData = [
                'email' => $validatedData['contact_details']['email'],
                'phone' => $validatedData['contact_details']['phone'],
                // Set nullable fields to null for XMLAgency
                'gender' => null,
                'firstname' => null,  
                'lastname' => null,
                'address' => null,
            ];
            $booking->contactDetail()->create($contactData);
        }

        // Create travellers (map XMLAgency fields to model)
        if (!empty($validatedData['travellers'])) {
            $travellersData = [];
            foreach ($validatedData['travellers'] as $traveller) {
                $travellersData[] = [
                    'birthdate' => $traveller['birthdate'],
                    'passport_number' => $traveller['passport_number'],
                    'nationality' => $traveller['nationality'], // XMLAgency uses 3-letter codes
                    'firstname' => $traveller['firstname'],
                    'lastname' => $traveller['lastname'],
                    'middlename' => $traveller['middlename'] ?? null,
                    'gender' => $traveller['gender'],
                    // Set XMLAgency-unnecessary fields to null
                    'passport_expiry_date' => null,
                    'passport_country' => null,
                ];
            }
            $booking->travellers()->createMany($travellersData);
        }

        Log::info('XMLAgency booking created', [
            'booking_reference' => $booking->booking_reference,
            'supplier_reference' => $booking->supplier_reference,
            'price' => $actualPrice
        ]);

        return [
            'success' => true,
            'booking' => $booking,
        ];
    }



    /**
     * Build journey data from segments (similar to FlightSearchService)
     */
    private function buildJourneyData(array $segments): array
    {
        if (empty($segments)) {
            return [];
        }

        // Get departure and arrival info first
        $firstSegment = $segments[0];
        $lastSegment = end($segments);

        $departureDateTime = Carbon::createFromFormat('d.m.Y H:i', $firstSegment['Departure']['Date']['value']);
        $arrivalDateTime = Carbon::createFromFormat('d.m.Y H:i', $lastSegment['Arrival']['Date']['value']);

        // Calculate total duration using actual departure and arrival times
        $totalMinutes = $departureDateTime->diffInMinutes($arrivalDateTime);
        $totalHours = intval($totalMinutes / 60);
        $remainingMinutes = $totalMinutes % 60;

        // Calculate stops
        $stops = [];
        $stopsCount = count($segments) - 1;

        for ($i = 0; $i < count($segments) - 1; $i++) {
            $currentSegment = $segments[$i];
            $nextSegment = $segments[$i + 1];

            $arrivalTime = Carbon::createFromFormat('d.m.Y H:i', $currentSegment['Arrival']['Date']['value']);
            $departureTime = Carbon::createFromFormat('d.m.Y H:i', $nextSegment['Departure']['Date']['value']);

            // Calculate layover duration correctly (departure - arrival)
            $layoverDuration = $arrivalTime->diffInMinutes($departureTime);

            // Ensure we don't have negative durations
            if ($layoverDuration < 0) {
                $layoverDuration = 0;
            }

            $layoverHours = intval($layoverDuration / 60);
            $layoverMinutesRemainder = $layoverDuration % 60;

            $stopAirportCode = $currentSegment['Arrival']['Iata']['value'];
            $stopAirportInfo = [
                'Code' => $stopAirportCode,
                'Name' => $currentSegment['Arrival']['Name']['value'] ?? '',
                'City' => $currentSegment['Arrival']['City']['value'] ?? ''
            ];

            $stops[] = [
                'Location' => $stopAirportInfo,
                'Duration' => [
                    'Hours' => $layoverHours,
                    'Minutes' => $layoverMinutesRemainder
                ]
            ];
        }

        // Build segments data
        $segmentsData = [];
        foreach ($segments as $segment) {
            // Handle baggage information (might not be present)
            $checkedBaggage = null;
            if (isset($segment['Baggage'])) {
                $baggageType = $segment['Baggage']['BaggageType']['value'] ?? 'Unknown';
                $baggageCount = $segment['Baggage']['Count']['value'] ?? 0;

                $checkedBaggage = [
                    'Type' => $baggageType,
                    'Count' => $baggageType === 'Nil' ? 0 : intval($baggageCount),
                    'Description' => $this->getBaggageDescription($baggageType, $baggageCount)
                ];
            }

            // Handle cabin baggage information (might not be present)
            $cabinBaggage = null;
            if (isset($segment['CabinBaggage'])) {
                $cabinBaggageType = $segment['CabinBaggage']['BaggageType']['value'] ?? 'Unknown';
                $cabinBaggageCount = $segment['CabinBaggage']['Count']['value'] ?? 0;

                $cabinBaggage = [
                    'Type' => $cabinBaggageType,
                    'Count' => $cabinBaggageType === 'Nil' ? 0 : intval($cabinBaggageCount),
                    'Description' => $this->getBaggageDescription($cabinBaggageType, $cabinBaggageCount)
                ];
            }

            $airlineCode = $segment['MarketingAirline']['value'];
            $airlineInfo = [
                'Code' => $airlineCode,
                'Name' => $segment['MarketingAirlineName']['value'] ?? '',
                'Logo' => 'https://static.city.travel/aclogo/small/' . $airlineCode . '.png'
            ];

            $segmentsData[] = [
                'FlightNumber' => $segment['FlightNum']['value'],
                'Airline' => $airlineInfo,
                'Aircraft' => $segment['AirCraft']['value'],
                'Departure' => [
                    'Code' => $segment['Departure']['Iata']['value'],
                    'Name' => $segment['Departure']['Name']['value'] ?? '',
                    'City' => $segment['Departure']['City']['value'] ?? '',
                    'Date' => $segment['Departure']['Date']['value']
                ],
                'Arrival' => [
                    'Code' => $segment['Arrival']['Iata']['value'],
                    'Name' => $segment['Arrival']['Name']['value'] ?? '',
                    'City' => $segment['Arrival']['City']['value'] ?? '',
                    'Date' => $segment['Arrival']['Date']['value']
                ],
                'Duration' => $segment['FlightTime']['value'],
                'Class' => $segment['FlightClass']['value'],
                'Baggage' => [
                    'Checked' => $checkedBaggage,
                    'Cabin' => $cabinBaggage
                ]
            ];
        }

        return [
            'Duration' => [
                'Hours' => $totalHours,
                'Minutes' => $remainingMinutes
            ],
            'DepartDate' => [
                'Date' => $departureDateTime->format('d/m/Y'),
                'Time' => $departureDateTime->format('H:i')
            ],
            'ArriveDate' => [
                'Date' => $arrivalDateTime->format('d/m/Y'),
                'Time' => $arrivalDateTime->format('H:i')
            ],
            'Stops' => $stops,
            'StopsCount' => $stopsCount,
            'Segments' => $segmentsData
        ];
    }

    /**
     * Get baggage description
     */
    private function getBaggageDescription(string $type, $count): string
    {
        return match (strtolower($type)) {
            'unknown' => 'Baggage information unknown',
            'nil','nilselect' => 'All baggage for a fee',
            'kilos' => $count . ' kg',
            'pounds' => $count . ' lbs',
            'pieces' => $count . ' piece' . ($count > 1 ? 's' : ''),
            default => $count . ' ' . $type
        };
    }

    /**
     * Get booking details
     */
    public function getBookingDetails(string $bookId): array
    {
        $booking = FlightBooking::with('tickets')
            ->where('booking_reference', $bookId)
                            ->where('flight_type', FlightSupplier::XMLAGENCY)
            ->first();

        return $booking ? ['success' => true, 'data' => $booking] : ['success' => false, 'message' => 'Booking not found'];
    }
}
