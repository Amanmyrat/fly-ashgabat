<?php

namespace App\Services;

use App\Repositories\AirportDataRepositoryInterface;
use App\Services\TravelFusion\Requests\CheckRoutingRequestBuilder;
use App\Services\TravelFusion\Requests\StartRoutingRequestBuilder;
use App\Services\TravelFusion\TravelFusionService;
use Illuminate\Http\Client\ConnectionException;

class FlightSearchService
{
    private array $airports;
    private array $countries;

    public function __construct(
        protected TravelFusionService $travelFusionService,
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
                $supplier = $router['Supplier'] ?? '';
                $vendor = $router['Vendor'] ?? '';
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
                            $supplier,
                            $vendor,
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
                                $supplier,
                                $vendor,
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
        string $supplier,
        array  $vendor,
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
//            'Supplier' => $supplier,
//            'Vendor' => $vendor,
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
        foreach ($segmentData['SegmentList']['Segment'] ?? [] as $segment) {
            $operatorCode = strtolower($segment['Operator']['Code']);
            $supplierClass = $segment['TravelClass']['SupplierClass'] ?? '';

//            dd($supplierClass, $features, $direction);
            $relevantFeatures = $this->getRelevantFeatures($features, $supplierClass, $direction);

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
                'DepartDate' => $segment['DepartDate'] ?? '',
                'ArriveDate' => $segment['ArriveDate'] ?? '',
                'Duration' => $segment['Duration'] ?? '',
                'Operator' => [
                    'Name' => $segment['Operator']['Name'] ?? '',
                    'Code' => $segment['Operator']['Code'] ?? '',
                    'Logo' => "https://www.travelfusion.com/images/operators/p{$operatorCode}.gif",
                ],
                'FlightNumber' => ($segment['FlightId']['Code'] ?? ''),
                'TravelClass' => $segment['TravelClass'] ?? [],
                'Features' => $relevantFeatures,
            ];
        }

        return [
            'Id' => $segmentData['Id'] ?? '',
            'Price' => [
                'Amount' => $segmentData['Price']['Amount'] ?? '',
                'Currency' => $segmentData['Price']['Currency'] ?? '',
            ],
            'Duration' => $segmentData['Duration'] ?? '',
            'Segments' => $segments,
        ];
    }

    private function getRelevantFeatures(array $features, string $supplierClass, string $direction): array
    {
        $relevantFeatures = [];

        foreach ($features['Feature'] as $feature) {
            // Extract Feature Type and Options

            $featureType = $feature['@attributes']['Type'] ?? '';
            $options = $feature['Option'] ?? [];
            foreach ($options as $option) {
                $conditions = $option['Condition'] ?? [];

                $isMatch = true;

                foreach ($conditions as $condition) {
                    $conditionType = $condition['@attributes']['Type'] ?? '';
                    $conditionValue = $condition['@attributes']['Value'] ?? '';

                    // Match SupplierClass, TravellerType, and Direction
                    if ($conditionType === 'SupplierClass') {
                        // Handle multiple SupplierClass values by taking only the first part
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
                    $filteredConditions = $this->formatConditions($conditions);
                    $relevantFeatures[] = [
                        'Type' => $featureType,
                        'Details' => [
                            'Currency' => $option['@attributes']['Currency'] ?? '',
                            'Value' => $option['@attributes']['Value'] ?? '',
                            'Conditions' => $filteredConditions,
                        ],
                    ];
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
            if (in_array($type, ['Provision', 'ChargeModel', 'Phase', 'TravellerType', 'OperatorCode'])) {
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
