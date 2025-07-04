<?php

namespace App\Services\XMLAgency;

use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Models\FlightBooking;
use App\Models\User;
use App\Http\Requests\XMLAgency\AeroBookRequestBuilder;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
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
        $aeroBookRequest = (new AeroBookRequestBuilder($validatedData))->build();
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

        $actualPrice = [
            'Amount' => $bookingResult['FullPrice']['value'],
            'Currency' => config('xmlagency.currency', 'EUR')
        ];


        $offerInfo = $bookingResult['Offers']['OfferInfo'];
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

        $outwardData = null;
        $returnData = null;

        if (!empty($outwardSegments)) {
            $outwardData = $this->buildJourneyData($outwardSegments);
        }

        if (!empty($returnSegments)) {
            $returnData = $this->buildJourneyData($returnSegments);
        }

        $bookingData = [
            'user_id' => $user?->id ?? null,
            'booking_reference' => $bookingResult['BookId']['value'],
            'supplier_reference' => $bookingResult['BookGuid']['value'],
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

        if (!empty($validatedData['contact_details'])) {
            $contactData = [
                'email' => $validatedData['contact_details']['email'],
                'phone' => $validatedData['contact_details']['phone'],
                'gender' => null,
                'firstname' => null,
                'lastname' => null,
                'address' => null,
            ];
            $booking->contactDetail()->create($contactData);
        }

        if (!empty($validatedData['travellers'])) {
            $travellersData = [];
            foreach ($validatedData['travellers'] as $traveller) {
                $travellersData[] = [
                    'birthdate' => $traveller['birthdate'],
                    'passport_number' => $traveller['passport_number'],
                    'nationality' => $traveller['nationality'],
                    'firstname' => $traveller['firstname'],
                    'lastname' => $traveller['lastname'],
                    'middlename' => $traveller['middlename'] ?? null,
                    'gender' => $traveller['gender'],
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

        $totalMinutes = $departureDateTime->diffInMinutes($arrivalDateTime);
        $totalHours = intval($totalMinutes / 60);
        $remainingMinutes = $totalMinutes % 60;

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

        $segmentsData = [];
        foreach ($segments as $segment) {
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
}
