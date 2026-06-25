<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class MyAgentFlightPickCache
{
    public const TTL_MINUTES = 30;

    public static function key(string $flightId): string
    {
        return 'myagent_pick_' . md5($flightId);
    }

    public static function get(string $flightId): ?array
    {
        $cached = Cache::get(self::key($flightId));

        return is_array($cached) ? $cached : null;
    }

    public static function put(string $flightId, array $data): void
    {
        Cache::put(self::key($flightId), $data, now()->addMinutes(self::TTL_MINUTES));
    }
}
