<?php

namespace App\Services\XMLAgency;

use App\Enum\FlightSupplier;
use App\Enum\FlightType;
use App\Services\FlightMarkupService;
use App\Services\XMLAgency\RequestBuilder\AeroSearchRequestBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class FlightSearchService
{

    public function __construct(
        protected XMLAgencyService $xmlAgencyService,
        protected FlightMarkupService $markupService
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function search(array $validatedData): array
    {
        $requestBuilder = new AeroSearchRequestBuilder($validatedData);
        $requestData = $requestBuilder->build();
        $searchResponse = $this->xmlAgencyService->sendRequest($requestData, 'AeroSearch');

        if ($searchResponse['Success']['value'] != "true") {
            $errorMessage = $searchResponse['AeroSearchResult']['ErrorString'] ?? 'Search failed';
            return [
                'success' => false,
                'message' => $errorMessage,
                'data' => $searchResponse
            ];
        }

        return $this->processResponse($searchResponse, $validatedData);
    }

    private function processResponse(array $response, array $requestData): array
    {
        $flights = [];

        // Extract reference data
        $airportsData = $this->extractAirportsData($response);
        $airlinesData = $this->extractAirlinesData($response);

        if (isset($response['FlightData']['FlightData'])) {
            $flightData = $response['FlightData']['FlightData'];

            // Handle both single flight and array of flights
            if (!isset($flightData[0])) {
                $flightData = [$flightData];
            }

            foreach ($flightData as $flight) {
                $flights[] = $this->transformFlight($flight, $requestData, $airportsData, $airlinesData);
            }
        }

        Cache::put('search_guid' . $response['SearchGuid']['value'], now()->addMinutes(30));
        return [
            'success' => true,
            'search_guid' => $response['SearchGuid']['value'],
            'flights' => $flights
        ];
    }

    private function transformFlight(array $flight, array $requestData, array $airportsData, array $airlinesData): array
    {
        $offerInfo = $flight['Offers']['OfferInfo'];
        $offerInfo = is_array($offerInfo) && isset($offerInfo[0]) ? $offerInfo : [$offerInfo];

        // Split segments into outward and return based on offer-level or segment-level Rph
        $outwardSegments = [];
        $returnSegments = [];

        foreach ($offerInfo as $index => $offer) {
            if (isset($offer['Segments']['OfferSegment'])) {
                $segments = $offer['Segments']['OfferSegment'];
                $segments = is_array($segments) && isset($segments[0]) ? $segments : [$segments];

                // Determine journey type based on both segment and offer Rph
                foreach ($segments as $segment) {
                    $segmentRph = $segment['Rph']['value'] ?? null;
                    $offerRph = $offer['Rph']['value'] ?? null;

                    // Only outward if both segment=1 AND offer=1, otherwise return
                    if ($segmentRph && $offerRph) {
                        // Both exist - outward only if both are 1
                        if ($segmentRph == '1' && $offerRph == '1') {
                            $outwardSegments[] = $segment;
                        } else {
                            $returnSegments[] = $segment;
                        }
                    } elseif ($segmentRph) {
                        // Only segment Rph exists
                        if ($segmentRph == '1') {
                            $outwardSegments[] = $segment;
                        } else {
                            $returnSegments[] = $segment;
                        }
                    } elseif ($offerRph) {
                        // Only offer Rph exists
                        if ($offerRph == '1') {
                            $outwardSegments[] = $segment;
                        } else {
                            $returnSegments[] = $segment;
                        }
                    }
                }
            }
        }
        // Get origin and destination
        $origin = $outwardSegments[0]['Departure']['Iata']['value'];
        $destination = end($outwardSegments)['Arrival']['Iata']['value'];

        $offerInfo = $flight['Offers']['OfferInfo'];
        $offerInfo = is_array($offerInfo) && isset($offerInfo[0]) ? $offerInfo : [$offerInfo];

        $originalAmount = floatval($flight['TotalPrice']['value']);
        $priceWithMarkup = $this->markupService->applyMarkup(
            $originalAmount,
            'USD',
            FlightSupplier::XMLAGENCY,
            $offerInfo[0]['ValidatingAirline']['value']
        );

        // Build the transformed flight
        $transformedFlight = [
            'OfferCode' => $flight['OfferCode']['value'],
            'Origin' => $this->getAirportInfo($origin, $airportsData),
            'Destination' => $this->getAirportInfo($destination, $airportsData),
//            'TotalSum' => [
//                'Amount' => floatval($flight['TotalPrice']['value']),
//                'Currency' => 'USD'
//            ],
            'TotalSum' => $priceWithMarkup,
            'Outward' => $this->buildJourneyData($outwardSegments, $airportsData, $airlinesData),
        ];

        $isRoundTrip = ($requestData['flight_type'] ?? FlightType::ONE_WAY->value) === FlightType::ROUND_TRIP->value;

        // Add return journey if round-trip
        if ($isRoundTrip && !empty($returnSegments)) {
            $transformedFlight['Return'] = $this->buildJourneyData($returnSegments, $airportsData, $airlinesData);
        } else {
            $transformedFlight['Return'] = null;
        }

        return $transformedFlight;
    }

    private function buildJourneyData(array $segments, array $airportsData, array $airlinesData): array
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
            $stopAirportInfo = $this->getAirportInfo($stopAirportCode, $airportsData);

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
                    'Description' => $this->getBaggageDescription($baggageType, $baggageCount, app()->getLocale())
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
                    'Description' => $this->getBaggageDescription($cabinBaggageType, $cabinBaggageCount, app()->getLocale())
                ];
            }

            $airlineCode = $segment['MarketingAirline']['value'];
            $airlineInfo = $this->getAirlineInfo($airlineCode, $airlinesData);

            $segmentsData[] = [
                'FlightNumber' => $segment['FlightNum']['value'],
                'Airline' => $airlineInfo,
                'Departure' => array_merge(
                    $this->getAirportInfo($segment['Departure']['Iata']['value'], $airportsData),
                    ['Date' => $segment['Departure']['Date']['value']]
                ),
                'Arrival' => array_merge(
                    $this->getAirportInfo($segment['Arrival']['Iata']['value'], $airportsData),
                    ['Date' => $segment['Arrival']['Date']['value']]
                ),
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

    private function getBaggageDescription(string $type, $count, string $locale = 'en'): string
    {
        $isRussian = $locale === 'ru';

        return match (strtolower($type)) {
            'unknown' => $isRussian ? 'Информация о багаже неизвестна' : 'Baggage information unknown',
            'nil', 'nilselect' => $isRussian ? 'Весь багаж за доплату' : 'All baggage for a fee',
            'kilos' => $count . ($isRussian ? ' кг' : ' kg'),
            'pounds' => $count . ($isRussian ? ' фунт' . $this->getRussianPluralEnding($count, 'ов', '', 'а') : ' lbs'),
            'pieces' => $isRussian
                ? $count . ' мест' . $this->getRussianPluralEnding($count, '', 'о', 'а')
                : $count . ' piece' . ($count > 1 ? 's' : ''),
            default => $count . ' ' . $type
        };
    }

    private function getRussianPluralEnding(int $count, string $many, string $one, string $few): string
    {
        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return $many;
        }

        return match ($lastDigit) {
            1 => $one,
            2, 3, 4 => $few,
            default => $many
        };
    }

    private function extractAirportsData(array $response): array
    {
        $airports = [];

        if (isset($response['AirPorts']['AirPortInfo'])) {
            $airportsInfo = $response['AirPorts']['AirPortInfo'];
            $airportsInfo = is_array($airportsInfo) && isset($airportsInfo[0]) ? $airportsInfo : [$airportsInfo];

            foreach ($airportsInfo as $airport) {
                $code = $airport['Iata']['value'] ?? '';
                if ($code) {
                    $airports[$code] = [
                        'Code' => $code,
                        'Name' => $airport['Name']['value'] ?? '',
                        'City' => $airport['City']['value'] ?? '',
                    ];
                }
            }
        }

        return $airports;
    }

    private function extractAirlinesData(array $response): array
    {
        $airlines = [];

        if (isset($response['AirCompany']['CodeValue'])) {
            $airlinesInfo = $response['AirCompany']['CodeValue'];
            $airlinesInfo = is_array($airlinesInfo) && isset($airlinesInfo[0]) ? $airlinesInfo : [$airlinesInfo];

            foreach ($airlinesInfo as $airline) {
                $code = $airline['Code']['value'] ?? '';
                if ($code) {
                    $airlines[$code] = [
                        'Code' => $code,
                        'Name' => $airline['Value']['value'] ?? '',
                        'Logo' => 'https://static.city.travel/aclogo/small/' . $code . '.png'
                    ];
                }
            }
        }

        return $airlines;
    }

    private function getAirportInfo(string $code, array $airportsData): array
    {
        if (isset($airportsData[$code])) {
            return $airportsData[$code];
        }

        // Fallback if airport not found in response data
        return [
            'Code' => $code,
            'Name' => '',
            'City' => '',
        ];
    }

    private function getAirlineInfo(string $code, array $airlinesData): array
    {
        if (isset($airlinesData[$code])) {
            return $airlinesData[$code];
        }

        // Fallback if airline not found in response data
        return [
            'Code' => $code,
            'Name' => '',
            'Logo' => 'https://static.city.travel/aclogo/small/' . $code . '.png'
        ];
    }
}
