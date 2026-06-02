<?php

namespace App\Services\Geo;

use App\Models\Hotel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HotelNearbyService
{
    private const OVERPASS_ENDPOINTS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass.openstreetmap.ru/api/interpreter',
    ];

    private const CACHE_TTL_SECONDS = 86400; // 1 day

    public function getNearbyForHotel(Hotel $hotel): array
    {
        $lat = (float) $hotel->latitude;
        $lng = (float) $hotel->longitude;

        $cacheKey = "hotel_nearby:v3:{$hotel->hid}:{$lat}:{$lng}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($hotel, $lat, $lng) {
            $rawElements = array_merge(
                $this->fetchFromOverpass($this->buildOverpassQuery($lat, $lng)),
                $this->fetchFromOverpass($this->buildAirportOverpassQuery($lat, $lng)),
            );

            $places = $this->normalizeOverpassElements($rawElements, $lat, $lng);

            return [
                'hotel' => [
                    'hid' => $hotel->hid,
                    'etg_id' => $hotel->etg_id,
                    'latitude' => $lat,
                    'longitude' => $lng,
                ],
                'groups' => $this->buildGroups($places),
            ];
        });
    }

    /**
     * @return array<int, mixed>
     */
    private function fetchFromOverpass(string $query): array
    {
        foreach (self::OVERPASS_ENDPOINTS as $endpoint) {
            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'PetekBackend/1.0 contact:dev@petek.local',
                        'Accept' => 'application/json',
                    ])
                    ->get($endpoint, [
                        'data' => $query,
                    ]);

                if (!$response->successful()) {
                    Log::warning('Overpass request failed', [
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 500),
                    ]);

                    continue;
                }

                $json = $response->json();

                $elements = is_array($json['elements'] ?? null)
                    ? $json['elements']
                    : [];

                Log::info('Overpass response parsed', [
                    'endpoint' => $endpoint,
                    'elements_count' => count($elements),
                    'first_elements' => array_slice($elements, 0, 3),
                ]);

                return $elements;
            } catch (Throwable $e) {
                Log::warning('Overpass request exception', [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    private function buildOverpassQuery(float $lat, float $lng): string
    {
        return <<<OVERPASS
[out:json][timeout:20];
(
  node(around:3000,{$lat},{$lng})["railway"="subway_entrance"];
  node(around:3000,{$lat},{$lng})["station"="subway"];
  node(around:3000,{$lat},{$lng})["subway"="yes"];

  node(around:5000,{$lat},{$lng})["railway"="station"]["station"!="subway"];
  way(around:5000,{$lat},{$lng})["railway"="station"]["station"!="subway"];

  node(around:3000,{$lat},{$lng})["amenity"="restaurant"];
  node(around:3000,{$lat},{$lng})["amenity"="cafe"];
  node(around:3000,{$lat},{$lng})["amenity"="pharmacy"];
  node(around:3000,{$lat},{$lng})["amenity"="hospital"];
  node(around:3000,{$lat},{$lng})["shop"="supermarket"];
  node(around:3000,{$lat},{$lng})["shop"="mall"];
  node(around:3000,{$lat},{$lng})["leisure"="park"];
  node(around:3000,{$lat},{$lng})["leisure"="garden"];

  way(around:3000,{$lat},{$lng})["leisure"="park"];
  way(around:3000,{$lat},{$lng})["leisure"="garden"];

  node(around:5000,{$lat},{$lng})["tourism"="attraction"];
  node(around:5000,{$lat},{$lng})["tourism"="museum"];
  node(around:5000,{$lat},{$lng})["tourism"="gallery"];
  node(around:5000,{$lat},{$lng})["amenity"="theatre"];
  node(around:5000,{$lat},{$lng})["historic"="monument"];

  way(around:5000,{$lat},{$lng})["tourism"="attraction"];
  way(around:5000,{$lat},{$lng})["tourism"="museum"];
  way(around:5000,{$lat},{$lng})["tourism"="gallery"];
  way(around:5000,{$lat},{$lng})["amenity"="theatre"];
  way(around:5000,{$lat},{$lng})["historic"="monument"];
);
out center tags 80;
OVERPASS;
    }

    private function buildAirportOverpassQuery(float $lat, float $lng): string
    {
        return <<<OVERPASS
[out:json][timeout:15];
(
  node(around:70000,{$lat},{$lng})["aeroway"="aerodrome"];
  way(around:70000,{$lat},{$lng})["aeroway"="aerodrome"];
);
out center tags 10;
OVERPASS;
    }

    /**
     * @param array<int, mixed> $elements
     * @return array<int, array<string, mixed>>
     */
    private function normalizeOverpassElements(array $elements, float $hotelLat, float $hotelLng): array
    {
        $places = [];

        $debug = [
            'total_elements' => count($elements),
            'skipped_not_array' => 0,
            'skipped_no_name' => 0,
            'skipped_no_coordinates' => 0,
            'skipped_unknown_type' => 0,
            'accepted' => 0,
            'sample_unknown_tags' => [],
            'sample_accepted' => [],
        ];

        foreach ($elements as $element) {
            if (!is_array($element)) {
                $debug['skipped_not_array']++;
                continue;
            }

            $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];

            $name = $tags['name:en']
                ?? $tags['name']
                ?? $tags['official_name']
                ?? null;

            if (!is_string($name) || trim($name) === '') {
                $debug['skipped_no_name']++;
                continue;
            }

            $lat = isset($element['lat'])
                ? (float) $element['lat']
                : (isset($element['center']['lat']) ? (float) $element['center']['lat'] : null);

            $lng = isset($element['lon'])
                ? (float) $element['lon']
                : (isset($element['center']['lon']) ? (float) $element['center']['lon'] : null);

            if ($lat === null || $lng === null) {
                $debug['skipped_no_coordinates']++;
                continue;
            }

            $placeType = $this->detectPlaceType($tags);

            if ($placeType === null) {
                $debug['skipped_unknown_type']++;

                if (count($debug['sample_unknown_tags']) < 10) {
                    $debug['sample_unknown_tags'][] = [
                        'name' => $name,
                        'tags' => $tags,
                        'osm_type' => $element['type'] ?? null,
                        'osm_id' => $element['id'] ?? null,
                    ];
                }

                continue;
            }

            $distanceMeters = $this->distanceMeters($hotelLat, $hotelLng, $lat, $lng);

            $places[] = [
                'name' => trim($name),
                'latitude' => $lat,
                'longitude' => $lng,
                'distance_meters' => $distanceMeters,
                'distance_text' => $this->formatDistance($distanceMeters),
                'place_type' => $placeType,
                'osm_type' => $element['type'] ?? null,
                'osm_id' => $element['id'] ?? null,
            ];

            $debug['accepted']++;

            if (count($debug['sample_accepted']) < 10) {
                $debug['sample_accepted'][] = end($places);
            }
        }

        Log::info('Overpass normalization result', $debug);

        return $this->deduplicatePlaces($places);
    }

    /**
     * @param array<string, mixed> $tags
     */
    private function detectPlaceType(array $tags): ?string
    {
        if (($tags['aeroway'] ?? null) === 'aerodrome') {
            $aerodromeType = $tags['aerodrome'] ?? null;

            if (in_array($aerodromeType, ['international', 'public', 'regional'], true)) {
                return 'airport';
            }

            if (isset($tags['iata']) || isset($tags['icao'])) {
                return 'airport';
            }

            return 'airport';
        }

        if (
            ($tags['station'] ?? null) === 'subway'
            || ($tags['subway'] ?? null) === 'yes'
            || ($tags['railway'] ?? null) === 'subway_entrance'
        ) {
            return 'subway_station';
        }

        if (($tags['railway'] ?? null) === 'station') {
            return 'train_station';
        }

        if (($tags['tourism'] ?? null) === 'attraction') {
            return 'tourist_attraction';
        }

        if (($tags['tourism'] ?? null) === 'museum') {
            return 'museum';
        }

        if (($tags['tourism'] ?? null) === 'gallery') {
            return 'gallery';
        }

        if (($tags['amenity'] ?? null) === 'theatre') {
            return 'theatre';
        }

        if (($tags['historic'] ?? null) === 'monument') {
            return 'monument';
        }

        if (($tags['historic'] ?? null) === 'castle') {
            return 'castle';
        }

        if (($tags['amenity'] ?? null) === 'place_of_worship') {
            return 'place_of_worship';
        }

        if (($tags['leisure'] ?? null) === 'park') {
            return 'park';
        }

        if (($tags['leisure'] ?? null) === 'garden') {
            return 'garden';
        }

        if (($tags['shop'] ?? null) === 'mall') {
            return 'shopping_mall';
        }

        if (($tags['shop'] ?? null) === 'supermarket') {
            return 'supermarket';
        }

        if (($tags['amenity'] ?? null) === 'hospital') {
            return 'hospital';
        }

        if (($tags['amenity'] ?? null) === 'pharmacy') {
            return 'pharmacy';
        }

        if (($tags['amenity'] ?? null) === 'restaurant') {
            return 'restaurant';
        }

        if (($tags['amenity'] ?? null) === 'cafe') {
            return 'cafe';
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $places
     * @return array<int, array<string, mixed>>
     */
    private function buildGroups(array $places): array
    {
        $groups = [
            $this->makeGroup(
                groupType: 'whats_nearby',
                title: "What's nearby",
                places: $places,
                allowedTypes: [
                    'park',
                    'garden',
                    'shopping_mall',
                    'supermarket',
                    'hospital',
                    'pharmacy',
                    'restaurant',
                    'cafe',
                ],
                limit: 5,
            ),

            $this->makeGroup(
                groupType: 'points_of_interest',
                title: 'Places of interest',
                places: $places,
                allowedTypes: [
                    'tourist_attraction',
                    'museum',
                    'gallery',
                    'theatre',
                    'monument',
                    'castle',
                    'place_of_worship',
                ],
                limit: 10,
            ),

            $this->makeGroup(
                groupType: 'airports',
                title: 'Airports',
                places: $places,
                allowedTypes: [
                    'airport',
                ],
                limit: 5,
            ),

            $this->makeGroup(
                groupType: 'train_stations',
                title: 'Train stations',
                places: $places,
                allowedTypes: [
                    'train_station',
                ],
                limit: 6,
            ),

            $this->makeGroup(
                groupType: 'subway',
                title: 'Metro',
                places: $places,
                allowedTypes: [
                    'subway_station',
                ],
                limit: 6,
            ),
        ];

        Log::info('Nearby groups built', [
            'places_count' => count($places),
            'groups' => array_map(fn (array $group) => [
                'group_type' => $group['group_type'],
                'items_count' => count($group['items']),
            ], $groups),
        ]);

        return $groups;
    }

    /**
     * @param array<int, array<string, mixed>> $places
     * @param array<int, string> $allowedTypes
     * @return array<string, mixed>
     */
    private function makeGroup(
        string $groupType,
        string $title,
        array $places,
        array $allowedTypes,
        int $limit,
    ): array {
        $items = array_values(array_filter(
            $places,
            fn (array $place) => in_array($place['place_type'], $allowedTypes, true),
        ));

        usort($items, static fn (array $a, array $b) =>
            ($a['distance_meters'] ?? PHP_INT_MAX) <=> ($b['distance_meters'] ?? PHP_INT_MAX)
        );

        $items = array_slice($items, 0, $limit);

        return [
            'group_type' => $groupType,
            'title' => $title,
            'items' => $items,
        ];
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earthRadius = 6371000;

        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(
                pow(sin($latDelta / 2), 2)
                + cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)
            ));

        return (int) round($earthRadius * $angle);
    }

    private function formatDistance(int $meters): string
    {
        if ($meters < 1000) {
            return $meters . ' m';
        }

        $km = round($meters / 1000, 1);

        return $km . ' km';
    }

    /**
     * @param array<int, array<string, mixed>> $places
     * @return array<int, array<string, mixed>>
     */
    private function deduplicatePlaces(array $places): array
    {
        $seen = [];
        $result = [];

        foreach ($places as $place) {
            $key = mb_strtolower($place['name']) . ':' . $place['place_type'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $place;
        }

        return $result;
    }
}
