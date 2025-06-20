<?php

namespace App\Jobs;

use App\Services\TravelFusion\Requests\GetBranchSupplierListRequestBuilder;
use App\Services\TravelFusion\Requests\ListSupplierRoutesRequestBuilder;
use App\Services\TravelFusion\SupplierRouteService;
use App\Services\TravelFusion\TravelFusionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CacheSupplierRoutesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('low');
    }

    /**
     * Execute the job.
     */
    public function handle(TravelFusionService $travelFusionService, SupplierRouteService $routeService): void
    {
        try {
            // Collect all supplier routes
            $allSupplierRoutes = [];

            // Step 1: Get list of suppliers
            $supplierListRequest = (new GetBranchSupplierListRequestBuilder())->build();
            $supplierListResponse = $travelFusionService->sendRequest($supplierListRequest);

            if (!isset($supplierListResponse['GetBranchSupplierList']['BranchSupplierList']['Supplier'])) {
                Log::error('Failed to get supplier list from TravelFusion');
                return;
            }

            $suppliers = $supplierListResponse['GetBranchSupplierList']['BranchSupplierList']['Supplier'];
            $suppliers = is_array($suppliers) ? $suppliers : [$suppliers];

            // Step 2: For each supplier, get their routes
            foreach ($suppliers as $supplier) {
                $supplierCode = $supplier;

                $routesRequest = (new ListSupplierRoutesRequestBuilder($supplierCode, false))->build();
                $routesResponse = $travelFusionService->sendRequest($routesRequest);

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
            $routeService->cacheRoutes($allSupplierRoutes);

            Log::info('Successfully cached routes for ' . count($allSupplierRoutes) . ' suppliers');

        } catch (\Exception $e) {
            Log::error('Error caching supplier routes: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
