<?php

namespace App\Http\Controllers\TFusion;

use App\Http\Controllers\BaseController;
use App\Http\Requests\FlightSearchRequest;
use App\Services\FlightSearchService;
use App\Services\SupplierRouteService;
use App\Services\TravelFusion\Requests\GetBranchSupplierListRequestBuilder;
use App\Services\TravelFusion\Requests\ListSupplierRoutesRequestBuilder;
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

    public function searchTest(): JsonResponse
    {
        try {
            // Collect all supplier routes
            $allSupplierRoutes = [];

            // Step 1: Get list of suppliers
            $supplierListRequest = (new GetBranchSupplierListRequestBuilder())->build();
            $supplierListResponse = $this->travelFusionService->sendRequest($supplierListRequest);

            if (!isset($supplierListResponse['GetBranchSupplierList']['BranchSupplierList']['Supplier'])) {
                Log::error('Failed to get supplier list from TravelFusion');
            }

            $suppliers = $supplierListResponse['GetBranchSupplierList']['BranchSupplierList']['Supplier'];
            $suppliers = is_array($suppliers) ? $suppliers : [$suppliers];

            // Step 2: For each supplier, get their routes
            foreach ($suppliers as $supplier) {
                $supplierCode = $supplier;

                $routesRequest = (new ListSupplierRoutesRequestBuilder($supplierCode, false))->build();
                $routesResponse = $this->travelFusionService->sendRequest($routesRequest);

                if (!isset($routesResponse['ListSupplierRoutes']['RouteList'])) {
                    Log::error("Failed to get routes for supplier: {$supplierCode}");
                    continue;
                }

                $routeList = $routesResponse['ListSupplierRoutes']['RouteList'];

                // Process airport routes
                $airportRoutes = [];
                if (isset($routeList['AirportRoutes'])) {
                    $routes = explode("\n", trim($routeList['AirportRoutes']));

                    foreach ($routes as $route) {
                        $route = trim($route);
                        if (strlen($route) === 6) {
                            $origin = substr($route, 0, 3);
                            $destination = substr($route, 3, 3);
                            $airportRoutes[] = [
                                'origin' => $origin,
                                'destination' => $destination
                            ];
                        }
                    }
                }

                // Process city routes
                $cityRoutes = [];
                if (isset($routeList['CityRoutes'])) {
                    $routes = explode("\n", trim($routeList['CityRoutes']));
                    foreach ($routes as $route) {
                        $route = trim($route);
                        if (strlen($route) === 6) {
                            $origin = substr($route, 0, 3);
                            $destination = substr($route, 3, 3);
                            $cityRoutes[] = [
                                'origin' => $origin,
                                'destination' => $destination
                            ];
                        }
                    }
                }

                // Store routes for this supplier
                $allSupplierRoutes[$supplierCode] = [
                    'airport_routes' => $airportRoutes,
                    'city_routes' => $cityRoutes,
                    'cached_at' => now()->toIso8601String()
                ];
            }

            // Use our new method to store all routes efficiently without duplicates
            $this->supplierRouteService->cacheRoutes($allSupplierRoutes);

            Log::info('Successfully cached routes for ' . count($allSupplierRoutes) . ' suppliers');

        } catch (\Exception $e) {
            Log::error('Error caching supplier routes: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }

        return new JsonResponse('success');
    }

}
