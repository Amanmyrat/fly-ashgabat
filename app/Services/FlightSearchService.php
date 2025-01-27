<?php

namespace App\Services;

use App\Repositories\AirportDataRepositoryInterface;
use App\Services\TravelFusion\Requests\CheckRoutingRequestBuilder;
use App\Services\TravelFusion\Requests\StartRoutingRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;

use DateTime;
use Illuminate\Http\Client\ConnectionException;

class FlightSearchService
{
    private array $airports;
    private array $countries;

    public function __construct(
        protected TravelFusionService            $travelFusionService,
        protected AirportDataRepositoryInterface $airportDataRepository
    )
    {
        $this->airports = $this->airportDataRepository->getAllAirports();
        $this->countries = $this->airportDataRepository->getAllCountries();
    }

    /**
     * @throws ConnectionException
     */
    public function search(array $validatedData): array
    {
        // Step 1: StartRouting
        $startRoutingRequest = (new StartRoutingRequestBuilder($validatedData))->build();
        $startRoutingResponse = $this->travelFusionService->sendRequest($startRoutingRequest);

        if (!isset($startRoutingResponse['StartRouting']['RouterList'])) {
            return ['message' => 'No result found'];
        }

        // Step 2: CheckRouting
        $routingId = $startRoutingResponse['StartRouting']['RoutingId'];
        $checkRoutingRequest = (new CheckRoutingRequestBuilder($routingId))->build();
        $checkRoutingResponse = $this->travelFusionService->sendRequest($checkRoutingRequest);

        return $this->formatFlights($checkRoutingResponse);
    }

    private function formatFlights(array $flightsResponse): array
    {
        $formattedFlights = [];

        if (isset($flightsResponse['CheckRouting']['RouterList']['Router'])) {
            foreach ($flightsResponse['CheckRouting']['RouterList']['Router'] as $router) {
                $requestedLocations = $router['RequestedLocations'] ?? [];
                $origin = $requestedLocations['Origin'] ?? [];
                $destination = $requestedLocations['Destination'] ?? [];

                $groupList = $router['GroupList']['Group'] ?? [];
                $features = $router['Features'] ?? [];
                $outwardList = $groupList['OutwardList']['Outward'] ?? [];
                $returnList = $groupList['ReturnList']['Return'] ?? [];

                if (empty($returnList)) {
                    // No return flights, handle only outward flights
                    foreach ($outwardList as $outward) {
                        $formattedFlights[] = $this->formatFlightItem(
                            $origin,
                            $destination,
                            $outward,
                            null,
                            $features
                        );
                    }
                } else {
                    // Match outward flights with return flights
                    foreach ($outwardList as $outward) {
                        foreach ($returnList as $return) {
                            $formattedFlights[] = $this->formatFlightItem(
                                $origin,
                                $destination,
                                $outward,
                                $return,
                                $features
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
        array  $features
    ): array
    {
        $totalSum = $outward['Price']['Amount'] ?? 0;
        $currency = $outward['Price']['Currency'] ?? '';
        if ($return) {
            $totalSum += $return['Price']['Amount'] ?? 0;
        }

        return [
            'Origin' => [
                'Code' => $origin['Code'],
                'Airport' => $this->airports[$origin['Code']]['airportName'] ?? $this->airports[$origin['Code']]['cityName'],
                'Country' => $this->countries[$this->airports[$origin['Code']]['country']]['name'],
            ],
            'Destination' => [
                'Code' => $destination['Code'],
                'Airport' => $this->airports[$destination['Code']]['airportName'] ?? $this->airports[$destination['Code']]['cityName'],
                'Country' => $this->countries[$this->airports[$destination['Code']]['country']]['name'],
            ],
            'TotalSum' => [
                'Amount' => $totalSum,
                'Currency' => $currency,
            ],
            'Outward' => $this->formatSegmentDetails($outward, $features, 'Outbound'),
            'Return' => $return ? $this->formatSegmentDetails($return, $features, 'Inbound') : null,
        ];
    }

    private function formatSegmentDetails(array $segmentData, array $features, string $direction): array
    {
        $segments = [];
        $stopDurations = []; // To calculate stop durations between segments
        $firstSegment = null;
        $lastSegment = null;

        $segmentsList = $segmentData['SegmentList']['Segment'] ?? [];

        $segmentsList = isset($segmentsList[0]) ? $segmentsList : [$segmentsList];

        foreach ($segmentsList as $index => $segment) {

            $operatorCode = strtolower($segment['TfOperator']['Code']);
            $supplierClass = $segment['TravelClass']['SupplierClass'] ?? '';

            $relevantFeatures = $this->getRelevantFeatures($features, $supplierClass, $direction);

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
                    'Airport' => $this->airports[$segment['Origin']['Code']]['airportName'] ?? $this->airports[$segment['Origin']['Code']]['cityName'],
                    'Country' => $this->countries[$this->airports[$segment['Origin']['Code']]['country']]['name'],
                ],
                'Destination' => [
                    'Code' => $segment['Destination']['Code'],
                    'Airport' => $this->airports[$segment['Destination']['Code']]['airportName'] ?? $this->airports[$segment['Destination']['Code']]['cityName'],
                    'Country' => $this->countries[$this->airports[$segment['Destination']['Code']]['country']]['name'],
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
                    'Name' => $segment['TfOperator']['Name'] ?? '',
                    'Code' => $segment['TfOperator']['Code'] ?? '',
                    'Logo' => "https://www.travelfusion.com/images/operators/p{$operatorCode}.gif",
                ],
                'FlightNumber' => ($segment['FlightId']['Code'] ?? ''),
                'TravelClass' => $segment['TravelClass'] ?? [],
                'Features' => $relevantFeatures,
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
                    'Airport' => $this->airports[$destinationCode]['airportName'] ?? $this->airports[$destinationCode]['cityName'],
                    'Country' => $this->countries[$this->airports[$destinationCode]['country']]['name'],
                ],
                'Duration' => [
                    'Hours' => $hours,
                    'Minutes' => $minutes,
                ],
            ];
        }

        return $stops;
    }

//    private function getRelevantFeatures(array $features, string $supplierClass, string $direction): array
//    {
//        $relevantFeatures = [];
//
//        foreach ($features['Feature'] as $feature) {
//            // Extract Feature Type and Options
//
//            $featureType = $feature['@attributes']['Type'] ?? '';
//            $options = $feature['Option'] ?? [];
//            foreach ($options as $option) {
//                $conditions = $option['Condition'] ?? [];
//
//                $isMatch = true;
//
//                foreach ($conditions as $condition) {
//                    $conditionType = $condition['@attributes']['Type'] ?? '';
//                    $conditionValue = $condition['@attributes']['Value'] ?? '';
//
//                    // Match SupplierClass, TravellerType, and Direction
//                    if ($conditionType === 'SupplierClass') {
//                        // Handle multiple SupplierClass values by taking only the first part
//                        $conditionValueFirstPart = explode(',', $conditionValue)[0];
//                        if ($conditionValueFirstPart !== $supplierClass) {
//                            $isMatch = false;
//                            break;
//                        }
//                    }
//
//                    if ($conditionType === 'Direction' && $conditionValue !== $direction) {
//                        $isMatch = false;
//                        break;
//                    }
//                }
//
//                if ($isMatch) {
//                    $filteredConditions = $this->formatConditions($conditions);
//                    $relevantFeatures[] = [
//                        'Type' => $featureType,
//                        'Details' => [
//                            'Currency' => $option['@attributes']['Currency'] ?? '',
//                            'Value' => $option['@attributes']['Value'] ?? '',
//                            'Conditions' => $filteredConditions,
//                        ],
//                    ];
//                }
//            }
//        }
//
//        return $relevantFeatures;
//    }

    private function getRelevantFeatures(array $features, string $supplierClass, string $direction): array
    {
        $relevantFeatures = [
            'HoldBag' => false,
            'SmallCabinBag' => false,
            'LargeCabinBag' => false,
        ];

        foreach ($features['Feature'] as $feature) {
            $featureType = $feature['@attributes']['Type'] ?? '';
            $options = $feature['Option'] ?? [];

            // Skip if the feature type is not in the required list
            if (!in_array($featureType, ['HoldBag', 'SmallCabinBag', 'LargeCabinBag'])) {
                continue;
            }

            foreach ($options as $option) {
                $conditions = $option['Condition'] ?? [];
                $value = $option['@attributes']['Value'] ?? '';

                // Only consider features where value is 0.00
                if ($value !== '0.00') {
                    continue;
                }

                $isMatch = true;

                foreach ($conditions as $condition) {
                    $conditionType = $condition['@attributes']['Type'] ?? '';
                    $conditionValue = $condition['@attributes']['Value'] ?? '';

                    // Match SupplierClass and Direction
                    if ($conditionType === 'SupplierClass') {
                        $conditionValueFirstPart = explode(',', $conditionValue)[0];
                        if ($conditionValueFirstPart !== $supplierClass) {
                            $isMatch = false;
                            break;
                        }
                    }

                    if ($conditionType === 'Direction' && $conditionValue !== $direction) {
                        $isMatch = false;
                        break;
                    }
                }

                if ($isMatch) {
                    // Format the feature based on conditions
                    $maxQuantity = null;
                    $maxWeight = null;

                    foreach ($conditions as $condition) {
                        if ($condition['@attributes']['Type'] === 'MaxQuantity') {
                            $maxQuantity = (int) $condition['@attributes']['Value'];
                        }
                        if ($condition['@attributes']['Type'] === 'MaxWeight') {
                            $maxWeight = $condition['@attributes']['Value'];
                        }
                    }

                    $formattedValue = $maxQuantity && $maxWeight ? "{$maxQuantity} x {$maxWeight}" : null;

                    $relevantFeatures[$featureType] = $formattedValue ? [
                        'value' => $formattedValue,
                    ] : false;
                }
            }
        }

        return $relevantFeatures;
    }


    private function formatConditions(array $conditions): array
    {
        $filteredConditions = [];

        foreach ($conditions as $condition) {
            $type = $condition['@attributes']['Type'] ?? '';
            $value = $condition['@attributes']['Value'] ?? '';

            // Skip unnecessary condition types
            if (in_array($type, ['Provision', 'ChargeModel', 'Phase', 'OperatorCode', 'SupplierClass', 'Direction', 'NumberOfSegments'])) {
                continue;
            }

            $filteredConditions[] = [
                'Type' => $type,
                'Value' => $value,
            ];
        }

        return $filteredConditions;
    }


}
