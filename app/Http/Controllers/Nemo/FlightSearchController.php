<?php

namespace App\Http\Controllers\Nemo;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Services\Nemo\FlightFilterService;
use App\Services\Nemo\FlightSortService;
use App\Services\Nemo\FlightSearchService;
use Cache;
use Exception;
use Illuminate\Http\JsonResponse;

class FlightSearchController extends Controller
{
    public function __construct(
        protected FlightSearchService       $searchFlightService,
        protected FlightFilterService       $flightFilterService,
        protected FlightSortService         $flightSortService
    )
    {
    }

    /**
     * Search for flights
     *
     * @param SearchRequest $request The HTTP request object containing search parameters.
     * @return JsonResponse JSON response containing search results.
     * @throws Exception
     */
    public function search(SearchRequest $request): JsonResponse
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        $filters = $request->input('filters', []);
        $sort = $request->input('sort', 'default');

        $queryParams = $request->except(['page', 'per_page', 'filters', 'sort', 'currency']);
        $cacheKey = $this->getCacheKey($queryParams);

        $result = Cache::remember($cacheKey, 60 * 5, function () use ($request) {
            return $this->searchFlightService->search($request->all());
        });
//        $result = $this->searchFlightService->search($request->all());

        if (isset($result['error']) && $result['error']) {
            return response()->json(['data' => $result['result']], 400);
        }

        $flightsData = $result['data']->Search_1_2Result->ResponseBody->PlaneFlights->Flight ?? [];

        $flightsData = is_array($flightsData) ? $flightsData : [$flightsData];

        $data = $this->flightFilterService->filterFlights($flightsData, $filters);

        $filterValues = $this->flightFilterService->getFilterValues($flightsData);

        $data = $this->flightFilterService->markCheapestAndFastestFlights($data);

        if ($sort != 'default') {
            $this->flightSortService->sortFlights($data, $sort);
        } else {
            usort($data, function ($a, $b) {
                // Sort logic: cheapest and fastest first, then normal order
                if ($a->isCheapest && !$b->isCheapest) return -1;
                if (!$a->isCheapest && $b->isCheapest) return 1;
                if ($a->isFastest && !$b->isFastest) return -1;
                if (!$a->isFastest && $b->isFastest) return 1;
                return 0;
            });
        }

        $transformedFareFamilies = $this->transformFareFamilies($result['fare_families_description']);
        $paginatedData = $this->paginateFlights($data, $transformedFareFamilies, $page, $perPage, $filterValues, $request->all());

        return response()->json($paginatedData);
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
     * Paginate the flights' data.
     *
     * @param array $flightsData
     * @param array $fareFamilies
     * @param int $page
     * @param int $perPage
     * @param array $filterValues
     * @param array $requestValues
     * @return array
     */
    private function paginateFlights(array $flightsData, array $fareFamilies, int $page, int $perPage, array $filterValues, array $requestValues): array
    {
        $totalItems = count($flightsData);
        $startIndex = ($page - 1) * $perPage;
        $pagedFlights = array_slice($flightsData, $startIndex, $perPage);

        return [
            'data' => [
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => ceil($totalItems / $perPage),
                    'per_page' => $perPage,
                    'total' => $totalItems
                ],
                'flights' => $pagedFlights,
                'filters' => $filterValues,
                'fare_families_description' => $fareFamilies,
                'requested_values' => $requestValues,
            ]
        ];
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
