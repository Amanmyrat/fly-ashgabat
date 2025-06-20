<?php

namespace App\Services\TravelFusion;

use App\Jobs\CacheSupplierRoutesJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SupplierRouteService
{
    /**
     * Check if a route is supported
     *
     * @param string $origin Origin airport/city code
     * @param string $destination Destination airport/city code
     * @return bool
     */
    public function isRouteSupported(string $origin, string $destination): bool
    {
        // Get cached routes
        $routes = Cache::get('unique_routes', []);
        // If no routes cached, dispatch job to cache them but allow search to proceed
        if (empty($routes)) {
            CacheSupplierRoutesJob::dispatch()->onQueue('low');
            return true;
        }

        // Standardize format (sort alphabetically)
        $sortedCodes = [$origin, $destination];
        sort($sortedCodes);
        $routeKey = $sortedCodes[0] . '-' . $sortedCodes[1];

        // Simple lookup
        return isset($routes[$routeKey]);
    }

    /**
     * Store all unique routes from all suppliers
     *
     * @param array $allSupplierRoutes
     * @return void
     */
    public function cacheRoutes(array $allSupplierRoutes): void
    {
        // Create a single flat map of all unique routes
        $uniqueRoutes = [];

        // Process each supplier's routes
        foreach ($allSupplierRoutes as $routes) {
            // Process airport routes
            if (isset($routes['airport_routes'])) {
                foreach ($routes['airport_routes'] as $route) {
                    // Standardize route format (alphabetically sorted)
                    $sortedCodes = [$route['origin'], $route['destination']];
                    sort($sortedCodes);
                    $key = $sortedCodes[0] . '-' . $sortedCodes[1];

                    // Add to unique routes
                    $uniqueRoutes[$key] = true;
                }
            }

            // Process city routes
            if (isset($routes['city_routes'])) {
                foreach ($routes['city_routes'] as $route) {
                    // Standardize route format (alphabetically sorted)
                    $sortedCodes = [$route['origin'], $route['destination']];
                    sort($sortedCodes);
                    $key = $sortedCodes[0] . '-' . $sortedCodes[1];

                    // Add to unique routes
                    $uniqueRoutes[$key] = true;
                }
            }
        }

        // Store in cache with 24-hour expiration
        Cache::put('unique_routes', $uniqueRoutes, now()->addDay());

        Log::info('Cached ' . count($uniqueRoutes) . ' unique routes');
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        Cache::forget('unique_routes');
    }
}
