<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class FlightSearchCacheService
{
    private const CACHE_TAG = 'flight_search_results';

    public function get(string $key): mixed
    {
        return Cache::tags([self::CACHE_TAG])->get($key);
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        Cache::tags([self::CACHE_TAG])->put($key, $value, $ttlSeconds);
    }

    public function clear(): void
    {
        Cache::tags([self::CACHE_TAG])->flush();
    }
}
