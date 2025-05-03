<?php

namespace App\Http\Controllers\TFusion;

use App\Http\Controllers\BaseController;
use App\Http\Requests\FlightSearchRequest;
use App\Services\FlightSearchService;
use Illuminate\Http\JsonResponse;

class FlightSearchController extends BaseController
{
    public function __construct(protected FlightSearchService $flightSearchService)
    {
    }

    /**
     * Search tfusion flights
     *
     * @localizationHeader
     *
     * @param FlightSearchRequest $request
     * @return JsonResponse
     */
    public function search(FlightSearchRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        return $this->handleServiceCall(function () use ($validatedData) {
            $maxTries = 3;
            $result   = null;

            for ($i = 1; $i <= $maxTries; $i++) {
                $result = $this->flightSearchService->search($validatedData);

                // Add serviceTries count to the result
                $result['serviceTries'] = $i;

                // If flights found, return immediately
                if (!empty($result['flights'])) {
                    return $result;
                }
            }

            // Return the final result after 3 attempts (with empty flights if still empty)
            return $result;
        });
    }

}
