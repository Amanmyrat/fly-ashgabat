<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class IpGeolocationService
{
    private const CACHE_TTL = 86400; // 24 hours
    private const BASE_URL = 'http://ip-api.com/json/';

    public function getCountryCode(string $ip): string
    {
        // If IP is empty or invalid, return default
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'TM';
        }

        return Cache::remember('ip_country_' . $ip, self::CACHE_TTL, function () use ($ip) {
            try {
                $response = Http::get(self::BASE_URL . $ip);

                if ($response->successful()) {
                    $data = $response->json();

                    if ($data['status'] === 'success') {
                        return $data['countryCode'];
                    }
                }
            } catch (\Exception $e) {
            }

            // Default to Turkmenistan if lookup fails
            return 'TM';
        });
    }
}
