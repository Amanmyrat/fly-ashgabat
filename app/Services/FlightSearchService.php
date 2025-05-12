<?php

namespace App\Services;

use App\Repositories\AirportDataRepositoryInterface;
use App\Services\TravelFusion\Requests\CheckRoutingRequestBuilder;
use App\Services\TravelFusion\Requests\StartRoutingRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use DateTime;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class FlightSearchService
{
    private array $airports;
    private array $cities;
    private array $countries;
    private string $locale;

    public function __construct(
        protected TravelFusionService            $travelFusionService,
        protected AirportDataRepositoryInterface $airportDataRepository,
        protected FlightFeaturesService $featuresService,
        protected IpGeolocationService $ipGeolocationService,
        protected SupplierRouteService $supplierRouteService
    )
    {
        $this->airports = $this->airportDataRepository->getAllAirports();
        $this->cities = $this->airportDataRepository->getAllCities();
        $this->countries = $this->airportDataRepository->getAllCountries();
        $this->locale = App::getLocale();
    }

    /**
     * @throws ConnectionException
     */
    public function search(array $validatedData): array
    {
        // Check if route is supported by any supplier
        if (!$this->supplierRouteService->isRouteSupported(
            $validatedData['departure_code'],
            $validatedData['arrival_code']
        )) {
            return [
                'success' => false,
                'message' => 'No suppliers found for this route',
                'flights' => []
            ];
        }

        // Step 1: StartRouting
        $startRoutingResponse = $this->getStartRoutingResponse($validatedData);
        if (!isset($startRoutingResponse['StartRouting']['RouterList'])) {
            return ['success' => false, 'message' => 'No result found'];
        }

        // Step 2: CheckRouting (Loop Until All Complete)
        $routingId = $startRoutingResponse['StartRouting']['RoutingId'];
        $checkRoutingRequest = (new CheckRoutingRequestBuilder($routingId))->build();

        [$routers, $tries] = $this->pollForCompleteRouters($checkRoutingRequest);

        // Store in Cache
        $this->cacheRoutingType($routingId, $validatedData['flight_type']);

        // Format and return flights
        $flights = $this->formatFlights(array_values($routers));

        return [
            'success' => true,
            'routing_id' => $routingId,
            'tries' => $tries,
            'flights' => $flights,
        ];
    }

    /**
     * @throws ConnectionException
     */
    private function getStartRoutingResponse(array $validatedData): array
    {
        $startRoutingRequest = (new StartRoutingRequestBuilder(
            $validatedData,
            $this->ipGeolocationService,
            $this->airportDataRepository
        ))->build();

        return $this->travelFusionService->sendRequest($startRoutingRequest);
    }

    /**
     * @throws ConnectionException
     */
    private function pollForCompleteRouters(array $checkRoutingRequest): array
    {
        $allRouters = [];
        $completedRouters = [];
        $totalRouters = 0;
        $tries = 0;
        $maxTries = 15;

        do {
            $response = $this->travelFusionService->sendRequest($checkRoutingRequest);
            $tries++;

            if (!isset($response['CheckRouting']['RouterList']['Router'])) {
                break;
            }

            $currentRouters = $response['CheckRouting']['RouterList']['Router'];
            $currentRouters = isset($currentRouters[0]) ? $currentRouters : [$currentRouters];
            if ($tries === 1) {
                $totalRouters = count($currentRouters);
            }

            // Track completion status and store valid routers
            foreach ($currentRouters as $index => $router) {
                // Track completion status for all routers
                if (isset($router['Complete']) && $router['Complete'] === "true") {
                    $completedRouters[$index] = true;

                    // Only store routers that have valid Group data
                    if ($this->hasValidGroupData($router)) {
                        $allRouters[$index] = $router;
                    }
                }
            }

            // Stop if all routers are complete
            $allComplete = count($completedRouters) === $totalRouters;

            if (!$allComplete && $tries < $maxTries) {
                sleep(2);
            }

        } while (!$allComplete && $tries < $maxTries);

        return [$allRouters, $tries];
    }

    private function hasValidGroupData(array $router): bool
    {
        return isset($router['GroupList']['Group'])
            && !isset($router['GroupList']['@attributes']);
    }

    private function isValidCompleteRouter(array $router): bool
    {
        return isset($router['Complete'])
            && $router['Complete'] === "true"
            && $this->hasValidGroupData($router);
    }

    private function cacheRoutingType(string $routingId, string $flightType): void
    {
        Cache::put('routing_' . $routingId, $flightType, now()->addMinutes(30));
    }

    private function formatFlights(array $routers): array
    {
        $formattedFlights = [];

        foreach ($routers as $router) {
            $requestedLocations = $router['RequestedLocations'] ?? [];
            $origin = $requestedLocations['Origin'] ?? [];
            $destination = $requestedLocations['Destination'] ?? [];

            $groupList = $router['GroupList']['Group'] ?? [];
            $features = $router['Features'] ?? [];

            // Handle single group or array of groups
            $groups = isset($groupList[0]) ? $groupList : [$groupList];

            foreach ($groups as $group) {
                $outwardList = $group['OutwardList']['Outward'] ?? [];
                $returnList = $group['ReturnList']['Return'] ?? [];

                // Handle single outward/return or array of them
                $outwards = isset($outwardList[0]) ? $outwardList : [$outwardList];
                $returns = isset($returnList[0]) ? $returnList : [$returnList];

                // Check if single outward and return (price is in group) or multiple (price is in segments)
                $isSingleCombination = count($outwards) === 1 && (!empty($returns) && count($returns) === 1);
                $groupPrice = $isSingleCombination ? ($group['Price'] ?? null) : null;

                if (empty($returns)) {
                    // No return flights, handle only outward flights
                    foreach ($outwards as $outward) {
                        $formattedFlights[] = $this->formatFlightItem(
                            $origin,
                            $destination,
                            $outward,
                            null,
                            $features,
                            $groupPrice
                        );
                    }
                } else {
                    // Match outward flights with return flights
                    foreach ($outwards as $outward) {
                        foreach ($returns as $return) {
                            $formattedFlights[] = $this->formatFlightItem(
                                $origin,
                                $destination,
                                $outward,
                                $return,
                                $features,
                                $groupPrice
                            );
                        }
                    }
                }
            }
        }

        return $formattedFlights;
    }

    private function formatFlightItem(
        array  $origin,
        array  $destination,
        array  $outward,
        ?array $return,
        array  $features,
        ?array $groupPrice = null
    ): array
    {
        // If we have a group price, use it
        if ($groupPrice) {
            $totalSum = $groupPrice['Amount'] ?? 0;
            $currency = $groupPrice['Currency'] ?? '';
        } else {
            // Otherwise calculate from segments
            $totalSum = $outward['Price']['Amount'] ?? 0;
            $currency = $outward['Price']['Currency'] ?? '';
            if ($return) {
                $totalSum += $return['Price']['Amount'] ?? 0;
            }
        }

        // Calculate origin data
        $originData = [
            'Code' => $origin['Code'],
            'Airport' => $origin['Type'] === 'airport'
                ? ($this->airports[$origin['Code']]['airportName'][$this->locale]
                    ?? $this->airports[$origin['Code']]['cityName'][$this->locale]
                    ?? $this->airports[$origin['Code']]['airportName']['en']
                    ?? $this->airports[$origin['Code']]['cityName']['en'])
                : ($this->cities[$origin['Code']]['name'][$this->locale]
                    ?? $this->cities[$origin['Code']]['name']['en']),
            'Country' => $origin['Type'] === 'airport'
                ? ($this->countries[$this->airports[$origin['Code']]['country']]['name'][$this->locale]
                    ?? $this->countries[$this->airports[$origin['Code']]['country']]['name']['en'])
                : ($this->countries[$this->cities[$origin['Code']]['country']]['name'][$this->locale]
                    ?? $this->countries[$this->cities[$origin['Code']]['country']]['name']['en']),
        ];

        // Calculate destination data
        $destinationData = [
            'Code' => $destination['Code'],
            'Airport' => $destination['Type'] === 'airport'
                ? ($this->airports[$destination['Code']]['airportName'][$this->locale]
                    ?? $this->airports[$destination['Code']]['cityName'][$this->locale]
                    ?? $this->airports[$destination['Code']]['airportName']['en']
                    ?? $this->airports[$destination['Code']]['cityName']['en'])
                : ($this->cities[$destination['Code']]['name'][$this->locale]
                    ?? $this->cities[$destination['Code']]['name']['en']),
            'Country' => $destination['Type'] === 'airport'
                ? ($this->countries[$this->airports[$destination['Code']]['country']]['name'][$this->locale]
                    ?? $this->countries[$this->airports[$destination['Code']]['country']]['name']['en'])
                : ($this->countries[$this->cities[$destination['Code']]['country']]['name'][$this->locale]
                    ?? $this->countries[$this->cities[$destination['Code']]['country']]['name']['en']),
        ];

        return [
            'Origin' => $originData,
            'Destination' => $destinationData,
            'TotalSum' => [
                'Amount' => $totalSum,
                'Currency' => $currency,
            ],
            'Outward' => $this->formatSegmentDetails($outward, $features),
            'Return' => $return ? $this->formatSegmentDetails($return, $features) : null,
        ];
    }

    private function formatSegmentDetails(array $segmentData, array $features): array
    {
        $segments = [];
        $stopDurations = []; // To calculate stop durations between segments
        $firstSegment = null;
        $lastSegment = null;

        $segmentsList = $segmentData['SegmentList']['Segment'] ?? [];

        $segmentsList = isset($segmentsList[0]) ? $segmentsList : [$segmentsList];

        foreach ($segmentsList as $index => $segment) {

            $operator = $segment['VendingOperator'] ?? $segment['TfVendingOperator'];
            $operatorCode = strtolower($operator['Code']);
            $supplierClass = $segment['TravelClass']['SupplierClass'] ?? '';

            if (count($features)) {
                $relevantFeatures = $this->featuresService->getRelevantFeatures($features, $supplierClass, $operator['Code']);
            }

            // Store the first and last segment for date extraction
            if ($index === 0) {
                $firstSegment = $segment;
            }
            $lastSegment = $segment;


            $departDateTime = DateTime::createFromFormat('d/m/Y-H:i', $segment['DepartDate'] ?? '');
            $arrivalDateTime = DateTime::createFromFormat('d/m/Y-H:i', $segment['ArriveDate'] ?? '');

            $departDate = $departDateTime?->format('d/m/Y');
            $departTime = $departDateTime?->format('H:i');
            $arrivalDate = $arrivalDateTime?->format('d/m/Y');
            $arrivalTime = $arrivalDateTime?->format('H:i');

            $totalMinutes = (int)($segment['Duration'] ?? 0);

            $hours = floor($totalMinutes / 60);
            $minutes = $totalMinutes % 60;

            // Add current segment details
            $segments[] = [
                'Origin' => [
                    'Code' => $segment['Origin']['Code'],
                    'Airport' => $this->airports[$segment['Origin']['Code']]['airportName'][$this->locale]
                        ?? $this->airports[$segment['Origin']['Code']]['cityName'][$this->locale]
                            ?? $this->airports[$segment['Origin']['Code']]['airportName']['en']
                            ?? $this->airports[$segment['Origin']['Code']]['cityName']['en'], // Fallback to English
                    'Country' => $this->countries[$this->airports[$segment['Origin']['Code']]['country']]['name'][$this->locale]
                        ?? $this->countries[$this->airports[$segment['Origin']['Code']]['country']]['name']['en'], // Fallback to English
                ],
                'Destination' => [
                    'Code' => $segment['Destination']['Code'],
                    'Airport' => $this->airports[$segment['Destination']['Code']]['airportName'][$this->locale]
                        ?? $this->airports[$segment['Destination']['Code']]['cityName'][$this->locale]
                            ?? $this->airports[$segment['Destination']['Code']]['airportName']['en']
                            ?? $this->airports[$segment['Destination']['Code']]['cityName']['en'], // Fallback to English
                    'Country' => $this->countries[$this->airports[$segment['Destination']['Code']]['country']]['name'][$this->locale]
                        ?? $this->countries[$this->airports[$segment['Destination']['Code']]['country']]['name']['en'], // Fallback to English
                ],

                'Duration' => [
                    'Hours' => $hours,
                    'Minutes' => $minutes,
                ],
                'DepartDate' => [
                    'Date' => $departDate,
                    'Time' => $departTime,
                ],
                'ArriveDate' => [
                    'Date' => $arrivalDate,
                    'Time' => $arrivalTime,
                ],
                'Operator' => [
                    'Name' => $operator['Name'] ?? '',
                    'Code' => $operator['Code'] ?? '',
                    'Logo' => "https://www.travelfusion.com/images/operators/p{$operatorCode}.gif",
                ],
                'FlightNumber' => ($segment['FlightId']['Code'] ?? ''),
                'TravelClass' => $segment['TravelClass'] ?? [],
                'Features' => $relevantFeatures ?? [
                        "HoldBag" => false,
                        "SmallCabinBag" => false,
                        "LargeCabinBag" => false,
                        "FlightChange" => false,
                        "Cancellation" => false
                    ],
            ];

            // Calculate stop duration if it's not the first segment
            if ($index > 0) {
                $previousArrival = DateTime::createFromFormat('d/m/Y-H:i', $segmentData['SegmentList']['Segment'][$index - 1]['ArriveDate']);
                $currentDeparture = DateTime::createFromFormat('d/m/Y-H:i', $segment['DepartDate']);

                if ($previousArrival && $currentDeparture) {
                    $stopDurations[] = $currentDeparture->getTimestamp() - $previousArrival->getTimestamp();
                }
            }
        }

        // Extract DepartDate and ArriveDate from first and last segments
        $departDateTime = DateTime::createFromFormat('d/m/Y-H:i', $firstSegment['DepartDate'] ?? '');
        $arrivalDateTime = DateTime::createFromFormat('d/m/Y-H:i', $lastSegment['ArriveDate'] ?? '');

        $departDate = $departDateTime?->format('d/m/Y');
        $departTime = $departDateTime?->format('H:i');
        $arrivalDate = $arrivalDateTime?->format('d/m/Y');
        $arrivalTime = $arrivalDateTime?->format('H:i');

        $totalMinutes = (int)($segmentData['Duration'] ?? 0);

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return [
            'Id' => $segmentData['Id'] ?? '',
            'Price' => [
                'Amount' => $segmentData['Price']['Amount'] ?? '',
                'Currency' => $segmentData['Price']['Currency'] ?? '',
            ],
            'Duration' => [
                'Hours' => $hours,
                'Minutes' => $minutes,
            ],
            'DepartDate' => [
                'Date' => $departDate,
                'Time' => $departTime,
            ],
            'ArriveDate' => [
                'Date' => $arrivalDate,
                'Time' => $arrivalTime,
            ],
            'Stops' => count($segments) > 1 ? $this->formatStops($stopDurations, $segments) : null,
            'StopsCount' => count($segments) - 1,
            'Segments' => $segments,
        ];
    }

    private function formatStops(array $stopDurations, array $segments): array
    {
        $stops = [];
        foreach ($stopDurations as $index => $duration) {
            $destinationCode = $segments[$index]['Destination']['Code'] ?? '';

            $hours = intdiv($duration, 3600);
            $minutes = ($duration % 3600) / 60;

            $stops[] = [
                'Location' => [
                    'Code' => $destinationCode,
                    'Airport' => $this->airports[$destinationCode]['airportName'][$this->locale]
                        ?? $this->airports[$destinationCode]['cityName'][$this->locale]
                            ?? $this->airports[$destinationCode]['airportName']['en']
                            ?? $this->airports[$destinationCode]['cityName']['en'], // Fallback to English
                    'Country' => $this->countries[$this->airports[$destinationCode]['country']]['name'][$this->locale]
                        ?? $this->countries[$this->airports[$destinationCode]['country']]['name']['en'], // Fallback to English
                ],
                'Duration' => [
                    'Hours' => $hours,
                    'Minutes' => $minutes,
                ],
            ];
        }

        return $stops;
    }
}
