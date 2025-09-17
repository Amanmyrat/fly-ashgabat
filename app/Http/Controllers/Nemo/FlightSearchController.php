<?php

namespace App\Http\Controllers\Nemo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nemo\FlightSearchRequest;
use App\Services\Nemo\FlightFilterService;
use App\Services\Nemo\FlightSearchService;
use App\Services\Nemo\FlightSortService;
use Cache;
use Exception;
use Illuminate\Http\JsonResponse;

class FlightSearchController extends Controller
{
    public function __construct(
        protected FlightSearchService $searchFlightService,
        protected FlightFilterService $flightFilterService,
        protected FlightSortService   $flightSortService
    )
    {
    }

    /**
     * Search for flights
     *
     * @param FlightSearchRequest $request The HTTP request object containing search parameters.
     * @return JsonResponse JSON response containing search results.
     * @throws Exception
     */
    public function search(FlightSearchRequest $request): JsonResponse
    {
        $filters = $request->input('filters', []);
        $sort = $request->input('sort', 'default');

        $queryParams = $request->except(['filters', 'sort']);
        $cacheKey = $this->getCacheKey($queryParams);

        // Try cache first
        $result = Cache::get($cacheKey);

        // If not cached or cached result has no flights -> fetch fresh
        if (!$result) {
            $result = $this->searchFlightService->search($request->all());

            $flightsData = $result['data']->Search_1_2Result->ResponseBody->PlaneFlights->Flight ?? [];
            $flightsData = is_array($flightsData) ? $flightsData : [$flightsData];

            // Only cache if flights are not empty
            if (!empty($flightsData)) {
                Cache::put($cacheKey, $result, 60 * 5);
            }
        }

        if (isset($result['error']) && $result['error']) {
            return response()->json(['data' => $result['result']], 400);
        }

        $flightsData = $result['data']->Search_1_2Result->ResponseBody->PlaneFlights->Flight ?? [];
        $flightsData = is_array($flightsData) ? $flightsData : [$flightsData];

        $data = $this->flightFilterService->filterFlights($flightsData, $filters);
        $data = array_values($data);

        $filterValues = $this->flightFilterService->getFilterValues($flightsData);

        if ($sort != 'default') {
            $this->flightSortService->sortFlights($data, $sort);
        }

        $transformedFareFamilies = $this->transformFareFamilies($result['fare_families_description']);

        return response()->json([
            'data' => [
                'flights' => $data,
                'filters' => $filterValues,
                'fare_families_description' => $transformedFareFamilies,
                'requested_values' => $request->all(),
            ],
        ]);
    }


    /**
     * Generate a unique cache key based on the search query parameters.
     *
     * @param array $queryParams Query parameters for the search.
     * @return string
     */
    private function getCacheKey(array $queryParams): string
    {
        return 'flights_search_' . md5(serialize($queryParams));
    }

    /**
     * Transform fare families data into the desired format
     *
     * @param $fareFamiliesDescription
     * @return array
     */
    private function transformFareFamilies($fareFamiliesDescription): array
    {
        $transformedFareFamilies = [];
        if (is_array($fareFamiliesDescription)) {
            foreach ($fareFamiliesDescription as $fareFamily) {
                $handLuggage = null;
                $baggage = null;

                foreach ($fareFamily->UniversalParameters->FareFamilyParameter as $parameter) {
                    if ($parameter->Code === 'carry_on') {
                        $handLuggage = [
                            'IsFree' => $parameter->NeedToPay === 'Free',
                            'Ru' => $this->findLangItemValue($parameter->ShortDescription->LangItem, 'RU'),
                            'En' => $this->findLangItemValue($parameter->ShortDescription->LangItem, 'EN')
                        ];
                    } elseif ($parameter->Code === 'baggage') {
                        $baggage = [
                            'IsFree' => $parameter->NeedToPay === 'Free',
                            'Ru' => $this->findLangItemValue($parameter->ShortDescription->LangItem, 'RU'),
                            'En' => $this->findLangItemValue($parameter->ShortDescription->LangItem, 'EN')
                        ];
                    }
                }

                $transformedFareFamilies[] = [
                    'ID' => (string)$fareFamily->ID,
                    'Name' => $fareFamily->Name,
                    'HandLuggage' => $handLuggage,
                    'Baggage' => $baggage
                ];
            }
        }

        return $transformedFareFamilies;
    }

    /**
     * Find language item value by code
     *
     * @param object|array $langItems
     * @param string $code
     * @return string|null
     */
    private function findLangItemValue(object|array $langItems, string $code): ?string
    {
        if (!is_array($langItems)) {
            return $langItems->Code === $code ? $langItems->Value : null;
        }

        foreach ($langItems as $langItem) {
            if ($langItem->Code === $code) {
                return $langItem->Value;
            }
        }

        return null;
    }
}
