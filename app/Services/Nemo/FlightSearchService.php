<?php

namespace App\Services\Nemo;

use App\Services\GeoDataService;
use App\Services\Nemo\RequestGenerate\SearchRequestGenerateService;

class FlightSearchService
{
    public function __construct(
        protected SearchRequestGenerateService $searchRequestGenerateService,
        protected SoapService       $soapService,
        protected GeoDataService    $geoDataService,
    )
    {
    }

    /**
     * Search for flights based on the provided request parameters.
     *
     * @param array $requestData The array containing search request data.
     * @return array The generated search response data.
     */
    public function search(array $requestData): array
    {
        $generatedRequest = $this->searchRequestGenerateService->generateSearchRequest($requestData);

        $result = $this->soapService->callSoap($generatedRequest, 'Search_1_2');

        if (isset($result->Search_1_2Result->Errors)) {
            return ['error' => true, 'result' => $result];
        }

        $this->unsetWarningsAndSearchData($result);

        $flightCount = 0;

        if (isset($result->Search_1_2Result->ResponseBody->PlaneFlights)) {
            $this->processPlaneFlights($result->Search_1_2Result->ResponseBody->PlaneFlights, $flightCount, $requestData);
        }

        return [
            'data' => $result,
            'flight_count' => $flightCount,
            'fare_families_description' => $result->Search_1_2Result->ResponseBody->FareFamiliesDescription->Description ?? null,
        ];

    }

    /**
     * Unset warnings and search data.
     *
     * @param mixed $result
     */
    private function unsetWarningsAndSearchData(mixed &$result): void
    {
        unset($result->Search_1_2Result->Warnings);
        unset($result->Search_1_2Result->ResponseBody->SearchData);
    }

    /**
     * Process plane flights.
     *
     * @param mixed $planeFlights
     * @param int $flight_count
     * @param array $requestData
     */
    private function processPlaneFlights(mixed &$planeFlights, int &$flight_count, array $requestData): void
    {
        if (is_array($planeFlights->Flight)) {
            foreach ($planeFlights->Flight as &$item) {
                $this->processFlightItem($item, $flight_count, $requestData);
            }
        } else {
            $item = $planeFlights->Flight;
            $this->processFlightItem($item, $flight_count, $requestData);
        }
    }

    /**
     * Process a single flight item.
     *
     * @param mixed $item
     * @param int $flight_count
     * @param array $requestData
     */
    private function processFlightItem(mixed &$item, int &$flight_count, array $requestData): void
    {
        $segmentsToProcess = is_array($item->Segments->Segment) ? $item->Segments->Segment : [$item->Segments->Segment];

        foreach ($segmentsToProcess as $segment) {
            $this->processSegmentData($segment);
        }

        $item->Outward = new \stdClass();
        $item->Return = null;

        if (in_array($item->TypeInfo->DirectionType, ['RT', 'SingleOJ', 'DoubleOJ'])) {
            $item->Return = new \stdClass();
        }

        $item->Outward->Segments = [];
        if ($item->Return) {
            $item->Return->Segments = [];
        }

        foreach ($segmentsToProcess as $segment) {
            if ($segment->RequestedSegment == 0) {
                $item->Outward->Segments[] = $segment;
            } elseif ($segment->RequestedSegment == 1 && $item->Return) {
                $item->Return->Segments[] = $segment;
            }
        }

        if (empty($item->Outward->Segments)) {
            $item->Outward->Segments = $segmentsToProcess;
        }

        $item->Outward->Stops = $this->calculateStops($item->Outward->Segments);
        $item->Outward->StopsCount = count($item->Outward->Stops);

        if ($item->Return) {
            $item->Return->Stops = $this->calculateStops($item->Return->Segments);
            $item->Return->StopsCount = count($item->Return->Stops);
        }

        unset($item->SourceID);
        unset($item->MandatoryLatinNames);

        $flight_count++;
    }

    /**
     * Calculate stops between segments
     *
     * @param array $segments
     * @return array
     */
    private function calculateStops(array $segments): array
    {
        $stops = [];

        if (count($segments) <= 1) {
            return $stops;
        }

        for ($i = 0; $i < count($segments) - 1; $i++) {
            $currentSegment = $segments[$i];
            $nextSegment = $segments[$i + 1];

            $arrivalTime = new \DateTime($currentSegment->ArrDateTime);
            $departureTime = new \DateTime($nextSegment->DepDateTime);
            $interval = $arrivalTime->diff($departureTime);

            $stops[] = [
                'Location' => $currentSegment->ArrAirp->AirportCode,
                'Duration' => [
                    'Hours' => $interval->h,
                    'Minutes' => $interval->i
                ]
            ];
        }

        return $stops;
    }

    /**
     * Process segment data with geo data.
     *
     * @param mixed $segment
     */
    private function processSegmentData(mixed &$segment): void
    {
        $data = $this->geoDataService->getInfo(
            $segment->DepAirp->AirportCode,
            $segment->ArrAirp->AirportCode,
            $segment->AircraftType,
            $segment->OpAirline,
            $segment->MarkAirline
        );

        $segment->DepAirp->AirportCode = $data['data']['depCode'];
        $segment->ArrAirp->AirportCode = $data['data']['arrCode'];
        $segment->OpAirline = $data['data']['opAirline'];
        $segment->MarkAirline = $data['data']['markAirline'];
        $segment->AircraftType = $data['data']['aircraftType'];
    }

}
