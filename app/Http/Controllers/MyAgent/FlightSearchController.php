<?php

namespace App\Http\Controllers\MyAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyAgent\FlightSearchRequest;
use App\Services\MyAgent\FlightFilterService;
use App\Services\MyAgent\FlightSearchService;
use App\Services\MyAgent\FlightSortService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FlightSearchController extends Controller
{
    public function __construct(
        protected FlightSearchService $flightSearchService,
        protected FlightFilterService $flightFilterService,
        protected FlightSortService $flightSortService
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

            $result = $this->flightSearchService->search($request->validated());

            $flights = $result['flights'];

            $filterValues = $this->flightFilterService->getFilterValues($flights);

            $flights = $this->flightFilterService->filterFlights($flights, $filters);

            if ($sort !== 'default') {
                $this->flightSortService->sortFlights($flights, $sort);
            }

            $flights = $this->flightFilterService->removeInternalFields($flights);

            return response()->json([
                'data' => [
                    'success' => $result['success'],
                    'flights' => array_values($flights),
                    'filters' => $filterValues,
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
