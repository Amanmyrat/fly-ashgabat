<?php

namespace App\Services;

use App\Enum\FlightSupplier;
use App\Repositories\AirportDataRepositoryInterface;
use Illuminate\Support\Facades\App;

class FlightBookingFormatterService
{
    private array $airports;
    private array $countries;

    public function __construct(
        protected AirportDataRepositoryInterface $airportDataRepository
    ) {
        $this->airports = $this->airportDataRepository->getAllAirports();
        $this->countries = $this->airportDataRepository->getAllCountries();
    }

    /**
     * Format booking based on flight supplier
     */
    public function formatBooking($booking): array
    {
        $baseData = [
            'id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'supplier_reference' => $booking->supplier_reference,
            'flight_type' => $booking->flight_type,
            'price' => $booking->price,
            'status' => $booking->status,
            'payment_type' => $booking->payment_type,
            'tickets' => $booking->tickets->map(fn($ticket) => [
                'id' => $ticket->id,
                'name' => $ticket->name,
                'ticket_url' => $ticket->ticket_url
            ]),
        ];

        return match($booking->flight_type) {
            FlightSupplier::TFUSION => $this->formatTFusionBooking($booking, $baseData),
            FlightSupplier::NEMO => $this->formatNemoBooking($booking, $baseData),
            FlightSupplier::XMLAGENCY => $this->formatXmlAgencyBooking($booking, $baseData),
            default => $baseData
        };
    }

    /**
     * Format TFusion booking (existing format)
     */
    private function formatTFusionBooking($booking, array $baseData): array
    {
        // Extract outward flight segments - handle both array and single object cases
        $outwardSegmentData = $booking->outward['SegmentList']['Segment'] ?? [];
        
        // Ensure segments is always an array of segments
        if (!empty($outwardSegmentData)) {
            // Check if it's a single segment object or array of segments
            // Single segment has properties like 'Origin', 'Duration', 'FlightId', etc.
            // Array of segments has numeric keys [0], [1], etc.
            if (isset($outwardSegmentData['Origin']) || isset($outwardSegmentData['FlightId'])) {
                // Single segment case - wrap in array
                $outwardSegments = [$outwardSegmentData];
            } else {
                // Multiple segments case (or already an array)
                $outwardSegments = $outwardSegmentData;
            }
        } else {
            $outwardSegments = [];
        }
        
        $firstOutwardSegment = $outwardSegments[0] ?? null;
        $lastOutwardSegment = end($outwardSegments) ?: $firstOutwardSegment;

        // Extract return flight segments if available
        $returnSegmentData = $booking->return['SegmentList']['Segment'] ?? null;
        $returnSegments = null;
        
        if ($returnSegmentData) {
            // Ensure return segments is always an array of segments
            if (isset($returnSegmentData['Origin']) || isset($returnSegmentData['FlightId'])) {
                // Single segment case - wrap in array
                $returnSegments = [$returnSegmentData];
            } else {
                // Multiple segments case (or already an array)
                $returnSegments = $returnSegmentData;
            }
        }
        
        $firstReturnSegment = $returnSegments[0] ?? null;
        $lastReturnSegment = $returnSegments ? end($returnSegments) : null;

        return array_merge($baseData, [
            'outward' => [
                'origin' => $this->formatAirport($firstOutwardSegment['Origin']['Code'] ?? null),
                'destination' => $this->formatAirport($lastOutwardSegment['Destination']['Code'] ?? null),
                'departureDate' => $this->splitDateTime($firstOutwardSegment['DepartDate'] ?? null),
                'arriveDate' => $this->splitDateTime($lastOutwardSegment['ArriveDate'] ?? null),
                'travelClass' => $firstOutwardSegment['TravelClass']['TfClass'] ?? null,
                'duration' => $booking->outward['Duration'] ?? null,
                'stops' => max(0, count($outwardSegments) - 1),
                'segments' => $this->formatTFusionSegments($outwardSegments)
            ],
            'return' => $returnSegments ? [
                'origin' => $this->formatAirport($firstReturnSegment['Origin']['Code'] ?? null),
                'destination' => $this->formatAirport($lastReturnSegment['Destination']['Code'] ?? null),
                'departureDate' => $this->splitDateTime($firstReturnSegment['DepartDate'] ?? null),
                'arriveDate' => $this->splitDateTime($lastReturnSegment['ArriveDate'] ?? null),
                'travelClass' => $firstReturnSegment['TravelClass']['TfClass'] ?? null,
                'duration' => $booking->return['Duration'] ?? null,
                'stops' => max(0, count($returnSegments) - 1),
                'segments' => $this->formatTFusionSegments($returnSegments)
            ] : null,
        ]);
    }

    /**
     * Format Nemo booking
     */
    private function formatNemoBooking($booking, array $baseData): array
    {
        $outwardSegments = $booking->outward['Segments'] ?? [];
        $firstOutwardSegment = $outwardSegments[0] ?? null;
        $lastOutwardSegment = end($outwardSegments) ?: $firstOutwardSegment;

        $returnSegments = $booking->return['Segments'] ?? null;
        $firstReturnSegment = $returnSegments[0] ?? null;
        $lastReturnSegment = $returnSegments ? end($returnSegments) : null;

        return array_merge($baseData, [
            'outward' => [
                'origin' => $this->formatAirport($firstOutwardSegment['Departure']['Code'] ?? null),
                'destination' => $this->formatAirport($lastOutwardSegment['Arrival']['Code'] ?? null),
                'departureDate' => $booking->outward['DepartDate'] ?? null,
                'arriveDate' => $booking->outward['ArriveDate'] ?? null,
                'travelClass' => $firstOutwardSegment['Class'] ?? null,
                'duration' => $this->formatNemoDuration($booking->outward['Duration'] ?? null),
                'stops' => $booking->outward['StopsCount'] ?? 0,
                'segments' => $this->formatNemoSegments($outwardSegments)
            ],
            'return' => $returnSegments ? [
                'origin' => $this->formatAirport($firstReturnSegment['Departure']['Code'] ?? null),
                'destination' => $this->formatAirport($lastReturnSegment['Arrival']['Code'] ?? null),
                'departureDate' => $booking->return['DepartDate'] ?? null,
                'arriveDate' => $booking->return['ArriveDate'] ?? null,
                'travelClass' => $firstReturnSegment['Class'] ?? null,
                'duration' => $this->formatNemoDuration($booking->return['Duration'] ?? null),
                'stops' => $booking->return['StopsCount'] ?? 0,
                'segments' => $this->formatNemoSegments($returnSegments)
            ] : null,
        ]);
    }

    /**
     * Format XMLAgency booking
     */
    private function formatXmlAgencyBooking($booking, array $baseData): array
    {
        $outwardSegments = $booking->outward['Segments'] ?? [];
        $firstOutwardSegment = $outwardSegments[0] ?? null;
        $lastOutwardSegment = end($outwardSegments) ?: $firstOutwardSegment;

        $returnSegments = $booking->return['Segments'] ?? null;
        $firstReturnSegment = $returnSegments[0] ?? null;
        $lastReturnSegment = $returnSegments ? end($returnSegments) : null;

        return array_merge($baseData, [
            'outward' => [
                'origin' => $this->formatAirport($firstOutwardSegment['Departure']['Code'] ?? null),
                'destination' => $this->formatAirport($lastOutwardSegment['Arrival']['Code'] ?? null),
                'departureDate' => $booking->outward['DepartDate'] ?? null,
                'arriveDate' => $booking->outward['ArriveDate'] ?? null,
                'travelClass' => $firstOutwardSegment['Class'] ?? null,
                'duration' => $this->formatXmlAgencyDuration($booking->outward['Duration'] ?? null),
                'stops' => $booking->outward['StopsCount'] ?? 0,
                'segments' => $this->formatXmlAgencySegments($outwardSegments)
            ],
            'return' => $returnSegments ? [
                'origin' => $this->formatAirport($firstReturnSegment['Departure']['Code'] ?? null),
                'destination' => $this->formatAirport($lastReturnSegment['Arrival']['Code'] ?? null),
                'departureDate' => $booking->return['DepartDate'] ?? null,
                'arriveDate' => $booking->return['ArriveDate'] ?? null,
                'travelClass' => $firstReturnSegment['Class'] ?? null,
                'duration' => $this->formatXmlAgencyDuration($booking->return['Duration'] ?? null),
                'stops' => $booking->return['StopsCount'] ?? 0,
                'segments' => $this->formatXmlAgencySegments($returnSegments)
            ] : null,
        ]);
    }

    /**
     * Format TFusion segments
     */
    private function formatTFusionSegments(array $segments): array
    {
        // If segments is empty or not a proper array, return empty array
        if (empty($segments) || !is_array($segments)) {
            return [];
        }

        return collect($segments)->map(function ($segment) {
            // Ensure segment is an array/object
            if (!is_array($segment) && !is_object($segment)) {
                return null;
            }

            // Convert object to array if needed
            $segmentArray = is_object($segment) ? (array)$segment : $segment;

            return [
                'airline' => [
                    'code' => $segmentArray['Operator']['Code'] ?? null,
                    'name' => $segmentArray['Operator']['Name'] ?? null,
                ],
                'flight_number' => $segmentArray['FlightId']['Code'] ?? null,
                'departure' => [
                    'airport' => $this->formatAirport($segmentArray['Origin']['Code'] ?? null),
                    'date' => $segmentArray['DepartDate'] ?? null,
                    'terminal' => $segmentArray['Origin']['Terminal'] ?? null,
                ],
                'arrival' => [
                    'airport' => $this->formatAirport($segmentArray['Destination']['Code'] ?? null),
                    'date' => $segmentArray['ArriveDate'] ?? null,
                    'terminal' => $segmentArray['Destination']['Terminal'] ?? null,
                ],
                'duration' => $segmentArray['Duration'] ?? null,
                'aircraft' => $segmentArray['AircraftType']['AircraftCode'] ?? null,
                'travel_class' => $segmentArray['TravelClass']['TfClass'] ?? null,
            ];
        })->filter()->toArray(); // Remove null entries
    }

    /**
     * Format Nemo segments
     */
    private function formatNemoSegments(array $segments): array
    {
        return collect($segments)->map(function ($segment) {
            return [
                'airline' => [
                    'code' => $segment['Airline']['Code'] ?? null,
                    'name' => null, // Not provided in Nemo data
                ],
                'flight_number' => $segment['FlightNumber'] ?? null,
                'departure' => [
                    'airport' => $this->formatAirport($segment['Departure']['Code'] ?? null),
                    'date' => $segment['Departure']['Date'] ?? null,
                ],
                'arrival' => [
                    'airport' => $this->formatAirport($segment['Arrival']['Code'] ?? null),
                    'date' => $segment['Arrival']['Date'] ?? null,
                ],
                'duration' => $segment['Duration'] ?? null,
                'aircraft' => $segment['Aircraft'] ?? null,
                'travel_class' => $segment['Class'] ?? null,
            ];
        })->toArray();
    }

    /**
     * Format XMLAgency segments
     */
    private function formatXmlAgencySegments(array $segments): array
    {
        return collect($segments)->map(function ($segment) {
            return [
                'airline' => [
                    'code' => $segment['Airline']['Code'] ?? null,
                    'name' => $segment['Airline']['Name'] ?? null,
                    'logo' => $segment['Airline']['Logo'] ?? null,
                ],
                'flight_number' => $segment['FlightNumber'] ?? null,
                'departure' => [
                    'airport' => $this->formatAirport($segment['Departure']['Code'] ?? null),
                    'date' => $segment['Departure']['Date'] ?? null,
                ],
                'arrival' => [
                    'airport' => $this->formatAirport($segment['Arrival']['Code'] ?? null),
                    'date' => $segment['Arrival']['Date'] ?? null,
                ],
                'duration' => $segment['Duration'] ?? null,
                'aircraft' => $segment['Aircraft'] ?? null,
                'travel_class' => $segment['Class'] ?? null,
                'baggage' => $segment['Baggage'] ?? null,
            ];
        })->toArray();
    }

    /**
     * Format Nemo duration
     */
    private function formatNemoDuration(?array $duration): ?string
    {
        if (!$duration) return null;
        
        $hours = $duration['Hours'] ?? 0;
        $minutes = $duration['Minutes'] ?? 0;
        
        return sprintf('%dh %dm', $hours, $minutes);
    }

    /**
     * Format XMLAgency duration
     */
    private function formatXmlAgencyDuration(?array $duration): ?string
    {
        if (!$duration) return null;
        
        $hours = $duration['Hours'] ?? 0;
        $minutes = $duration['Minutes'] ?? 0;
        
        return sprintf('%dh %dm', $hours, $minutes);
    }

    /**
     * Format airport details including country
     */
    private function formatAirport(?string $code): ?array
    {
        if (!$code || !isset($this->airports[$code])) {
            return null;
        }

        $locale = App::getLocale();
        $airportData = $this->airports[$code];
        $countryCode = $airportData['country'] ?? null;

        return [
            'code' => $code,
            'airport' => $airportData['airportName'][$locale]
                ?? $airportData['cityName'][$locale]
                    ?? $airportData['airportName']['en']
                    ?? $airportData['cityName']['en'],
            'country' => isset($this->countries[$countryCode])
                ? ($this->countries[$countryCode]['name'][$locale]
                    ?? $this->countries[$countryCode]['name']['en'])
                : null
        ];
    }

    /**
     * Split date and time from "DD/MM/YYYY-HH:MM" format (TFusion)
     */
    private function splitDateTime(?string $dateTime): ?array
    {
        if (!$dateTime || !str_contains($dateTime, '-')) {
            return null;
        }

        [$date, $time] = explode('-', $dateTime);
        return ['date' => $date, 'time' => $time];
    }
} 