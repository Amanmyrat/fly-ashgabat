<?php

namespace App\Http\Controllers;

use App\Http\Requests\FlightSearchRequest;
use App\Services\FlightSearchService;
use Exception;
use Illuminate\Http\JsonResponse;

class FlightSearchController extends Controller
{

    public function __construct(protected FlightSearchService $flightSearchService)
    {
    }

    /**
     * Search tfusion flights
     *
     * @param FlightSearchRequest $request
     * @return JsonResponse
     */
    public function search(FlightSearchRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        try {
            $response = $this->flightSearchService->search($validatedData);
            return new JsonResponse([
                'data' => [
                    'flights' => $response,
                    'requested_values' => $request->all(),
                ]
            ]);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
