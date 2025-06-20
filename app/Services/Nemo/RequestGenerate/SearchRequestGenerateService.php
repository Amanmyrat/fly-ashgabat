<?php

namespace App\Services\Nemo\RequestGenerate;

class SearchRequestGenerateService
{
    /**
     * Generate a search request for flight information.
     *
     * @param array $postRequest The input data for the search request.
     *
     * @return array The generated search request.
     */
    public function generateSearchRequest(array $postRequest): array
    {
        // Initialize the search request structure
        $searchRequest = $this->initializeSearchRequest();

        //  Set flight information
        $this->setFlightInformation($searchRequest, $postRequest);

        // Add passenger information if available
        $this->addPassengerInfo($searchRequest, $postRequest);

        // Set flight restrictions
        $this->setFlightRestrictions($searchRequest, $postRequest);

        return $searchRequest;
    }

    /**
     * Initialize the search request structure.
     *
     * @return array The initialized search request.
     */
    private function initializeSearchRequest(): array
    {
        return [
            'Search_1_2' => [
                'Request' => [
                    'Requisites' => [
                        'AuthToken' => config('nemo.auth_token'),
                    ],
                    'UserID' => config('nemo.user_id'),
                    'RequestBody' => []
                ]
            ]
        ];
    }

    /**
     * Set flight information in a flight search request.
     *
     * @param array $searchRequest The search request to update.
     * @param array $postRequest The input data for the search request.
     */
    private function setFlightInformation(array &$searchRequest, array $postRequest)
    {
        // Set flight type (0 - transit, 1 - direct)
//        $searchRequest['Search_1_2']['Request']['RequestBody']['RequestedFlightInfo']['Direct'] = $postRequest['is_direct_flight'];
        $searchRequest['Search_1_2']['Request']['RequestBody']['RequestedFlightInfo']['Direct'] = 0;
        $flightInfo =& $searchRequest['Search_1_2']['Request']['RequestBody']['RequestedFlightInfo'];

        $departureDate = date("Y-m-d\TH:i:s", strtotime($postRequest['departure_date_from']));
        $departureCityCode = $postRequest['departure_city_code'];
        $arrivalCityCode = $postRequest['arrival_city_code'];

        $odPair = [
            'DepatureDateTime' => $departureDate,
            'DepaturePoint' => ['Code' => $departureCityCode],
            'ArrivalPoint' => ['Code' => $arrivalCityCode],
        ];

        $flightInfo['ODPairs']['ODPair'][0] = $odPair;

        if (isset($postRequest['departure_date_to'])) {
            $this->addReturnDate($searchRequest, $postRequest);
        }
    }

    /**
     * Add return date information for round-trip flights.
     *
     * @param array $searchRequest The search request to update.
     * @param array $postRequest The input data for the search request.
     */
    private function addReturnDate(array &$searchRequest, array $postRequest)
    {
        $returnDate = date("Y-m-d\TH:i:s", strtotime($postRequest['departure_date_to']));
        $departureCityCode = $postRequest['departure_city_code'];
        $arrivalCityCode = $postRequest['arrival_city_code'];

        $returnDateInfo = [
            'DepatureDateTime' => $returnDate,
            'DepaturePoint' => ['Code' => $arrivalCityCode, 'IsCity' => 1],
            'ArrivalPoint' => ['Code' => $departureCityCode, 'IsCity' => 1],
        ];

        $searchRequest['Search_1_2']['Request']['RequestBody']['RequestedFlightInfo']['ODPairs']['ODPair'][] = $returnDateInfo;
    }

    /**
     * Add passenger information if available.
     *
     * @param array $searchRequest The search request to update.
     * @param array $postRequest The input data for the search request.
     */
    private function addPassengerInfo(array &$searchRequest, array $postRequest)
    {
        if ($postRequest['adults_count'] > 0) {
            $adults = ['Type' => 'ADT', 'Count' => $postRequest['adults_count']];
            $searchRequest['Search_1_2']['Request']['RequestBody']['Passengers'][] = $adults;
        }

        if ($postRequest['childs_count'] > 0) {
            $childs = ['Type' => 'CNN', 'Count' => $postRequest['childs_count']];
            $searchRequest['Search_1_2']['Request']['RequestBody']['Passengers'][] = $childs;
        }

        if ($postRequest['baby_without_a_seat'] > 0) {
            $babyWithoutASeat = ['Type' => 'INF', 'Count' => $postRequest['baby_without_a_seat']];
            $searchRequest['Search_1_2']['Request']['RequestBody']['Passengers'][] = $babyWithoutASeat;
        }

        if ($postRequest['baby_with_seat'] > 0) {
            $babyWithSeat = ['Type' => 'INS', 'Count' => $postRequest['baby_with_seat']];
            $searchRequest['Search_1_2']['Request']['RequestBody']['Passengers'][] = $babyWithSeat;
        }
    }

    /**
     * Set flight restrictions in a flight search request.
     *
     * @param array $searchRequest The search request to update.
     * @param array $postRequest The input data for the search request.
     */
    private function setFlightRestrictions(array &$searchRequest, array $postRequest)
    {
        if (isset($postRequest['class_type'])) {
            if ($postRequest['class_type'] === 'Economy') {
                $classType = ['ClassOfService' => ['Economy', 'PremiumEconomy']];
            } else {
                $classType = ['ClassOfService' => [$postRequest['class_type']]];
            }
            $searchRequest['Search_1_2']['Request']['RequestBody']['Restrictions']['ClassPreference'] = $classType;
        }

        $searchRequest['Search_1_2']['Request']['RequestBody']['Restrictions']['ResultsGrouping'] = 'None';
        $searchRequest['Search_1_2']['Request']['RequestBody']['Restrictions']['RequestorTags']['Tag'] = config('nemo.tags');
    }
}
