<?php

namespace App\Http\Controllers\TFusion;

use App\Http\Controllers\BaseController;
use App\Http\Requests\FlightSearchRequest;
use App\Services\TravelFusion\FlightSearchService;
use App\Services\TravelFusion\Requests\GetBranchSupplierListRequestBuilder;
use App\Services\TravelFusion\Requests\ListSupplierRoutesRequestBuilder;
use App\Services\TravelFusion\SupplierRouteService;
use App\Services\TravelFusion\TravelFusionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FlightSearchController extends BaseController
{
    public function __construct(protected FlightSearchService $flightSearchService, protected TravelFusionService $travelFusionService, protected SupplierRouteService $supplierRouteService)
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
