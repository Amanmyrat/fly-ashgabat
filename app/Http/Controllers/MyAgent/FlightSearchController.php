<?php

namespace App\Http\Controllers\MyAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyAgent\FlightSearchRequest;
use App\Services\FlightSearchCacheService;
use App\Services\MyAgent\FlightFilterService;
use App\Services\MyAgent\FlightSearchService;
use App\Services\MyAgent\FlightSortService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FlightSearchController extends Controller
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        protected FlightSearchService $flightSearchService,
        protected FlightFilterService $flightFilterService,
        protected FlightSortService $flightSortService,
        protected FlightSearchCacheService $flightSearchCacheService
    ) {
    }

    /**
     * Search for myagent flights
     *
     *
     * @param FlightSearchRequest $request
     * @return JsonResponse
     */
    public function search(FlightSearchRequest $request): JsonResponse
    {
        try {
            $filters = $request->input('filters', []);
            $sort = $request->input('sort', 'default');
            $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
            $page = max((int) $request->input('page', 1), 1);

            $searchParams = $request->except(['filters', 'sort', 'page', 'per_page']);
            $cacheKey = 'myagent_flights_search_' . md5(serialize($searchParams));

            $result = $this->flightSearchCacheService->get($cacheKey);

            if (!$result) {
                $result = $this->flightSearchService->search($request->validated());

                if (!empty($result['flights'])) {
                    $this->flightSearchCacheService->put($cacheKey, $result, self::CACHE_TTL_SECONDS);
                }
            }

            $allFlights = $result['flights'];

            $filterValues = $this->flightFilterService->getFilterValues($allFlights);

            $flights = $this->flightFilterService->filterFlights($allFlights, $filters);

            if ($sort !== 'default') {
                $this->flightSortService->sortFlights($flights, $sort);
            }

            $total = count($flights);
            $lastPage = (int) max(1, (int) ceil($total / $perPage));
            $page = min($page, $lastPage);
            $offset = ($page - 1) * $perPage;
            $pageFlights = array_slice($flights, $offset, $perPage);

            $pageFlights = $this->flightFilterService->removeInternalFields($pageFlights);

            return response()->json([
                'data' => [
                    'success' => $result['success'],
                    'flights' => array_values($pageFlights),
                    'filters' => $filterValues,
                    'pagination' => [
                        'current_page' => $page,
                        'last_page' => $lastPage,
                        'per_page' => $perPage,
                        'total' => $total,
                    ],
                    'requested_values' => $request->all(),
                    'meta' => $result['raw_meta'] ?? [],
                ],
            ]);
        } catch (Exception $exception) {
            Log::channel('myagent')->error('Flight search controller error ' . json_encode([
                    'message' => $exception->getMessage(),
                    'request' => $request->all(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return response()->json([
                'data' => [
                    'success' => false,
                    'message' => $exception->getMessage(),
                ],
            ], 400);
        }
    }
}
