<?php

namespace App\Services\ETG;

use App\DTO\FirstRateDTO;
use App\DTO\HotelSearchRequestDTO;
use App\DTO\HotelSearchResultDTO;
use App\DTO\RegionInfoDTO;
use App\Models\Hotel;
use App\Models\Region;

class HotelSearchService
{
    private const SERP_ENDPOINT = 'api/b2b/v3/search/serp/region/';

    public function __construct(private readonly EtgClient $client) {}

    /**
     * @return array{region: ?array, total_hotels: int, hotels: array<int, array>}
     */
    public function search(HotelSearchRequestDTO $dto): array
    {
        $response    = $this->client->post(self::SERP_ENDPOINT, $dto->toEtgBody());
        $data        = $response['data'] ?? $response;
        $hotels      = $data['hotels'] ?? [];
        $totalHotels = (int) ($data['total_hotels'] ?? 0);

        // Load the searched region once — it appears at the top level of the response,
        // not repeated inside each hotel.
        $lang          = $dto->language;
        $searchRegion  = Region::find($dto->regionId);
        $regionInfo    = null;
        if ($searchRegion) {
            $regionName  = $lang === 'ru' ? ($searchRegion->name_ru ?? $searchRegion->name_en) : ($searchRegion->name_en ?? $searchRegion->name_ru);
            $countryName = $lang === 'ru' ? ($searchRegion->country_name_ru ?? $searchRegion->country_name_en) : ($searchRegion->country_name_en ?? $searchRegion->country_name_ru);
            $regionInfo  = (new RegionInfoDTO(
                $searchRegion->id,
                $regionName,
                $searchRegion->type,
                $searchRegion->country_code,
                $countryName,
                $searchRegion->latitude,
                $searchRegion->longitude,
            ))->toArray();
        }

        if (empty($hotels)) {
            return ['region' => $regionInfo, 'total_hotels' => $totalHotels, 'hotels' => []];
        }

        $hids = array_map(fn (array $h) => (int) ($h['hid'] ?? $h['id'] ?? 0), $hotels);
        $hids = array_filter($hids, fn ($id) => $id > 0);

        $localHotels = Hotel::with(['reviewStats'])
            ->whereIn('hid', $hids)
            ->get()
            ->keyBy('hid');

        $regLat  = $searchRegion?->latitude;
        $regLong = $searchRegion?->longitude;
        $results = [];

        foreach ($hotels as $h) {
            $hid = (int) ($h['hid'] ?? 0);
            if ($hid <= 0) {
                continue;
            }

            $local = $localHotels->get($hid);
            if ($local === null) {
                continue;
            }

            $idStr     = $local->etg_id ?? (isset($h['id']) ? (string) $h['id'] : null);
            $hotelLat  = $local->latitude;
            $hotelLong = $local->longitude;

            $name    = $lang === 'ru' ? ($local->name_ru ?? $local->name_en) : ($local->name_en ?? $local->name_ru);
            $address = $lang === 'ru' ? ($local->address_ru ?? $local->address_en) : ($local->address_en ?? $local->address_ru);
            $images  = is_array($local->images) ? array_values($local->images) : [];

            $serpFilters = is_array($local->serp_filters) ? array_values($local->serp_filters) : [];
            if (empty($serpFilters)) {
                foreach ($h['rates'] ?? [] as $rate) {
                    foreach ($rate['serp_filters'] ?? [] as $sf) {
                        if (is_string($sf) && !in_array($sf, $serpFilters, true)) {
                            $serpFilters[] = $sf;
                        }
                    }
                }
            }

            $firstRateData = $this->extractFirstRate($h['rates'] ?? [], 'USD');
            $priceFrom     = $this->extractMinPrice($h['rates'] ?? []);
            $stats         = $local->reviewStats;
            $avgRating     = $stats?->avg_rating;

            $hr = (new HotelSearchResultDTO(
                hotelId: $hid,
                etgId: $idStr,
                latitude: $hotelLat,
                longitude: $hotelLong,
                name: $name ?? '',
                stars: $local->star_rating,
                priceFrom: $priceFrom['amount'],
                currency: $priceFrom['currency'] ?? 'USD',
                score: $avgRating,
                reviewsCount: ($stats?->reviews_count ?? 0) > 0 ? $stats->reviews_count : null,
                images: $images,
                serpFilters: $serpFilters,
                firstRate: $firstRateData,
                kind: $local->kind,
                address: $address,
            ))->toArray();

            $hr['avg_rating']        = $avgRating;
            $hr['score_qualitative'] = $avgRating !== null ? $this->scoreToQualitative($avgRating) : null;
            $results[] = $hr;
        }

        [$centerLat, $centerLong] = $this->resolveSearchCenter($regLat, $regLong, $results);
        foreach ($results as $i => $r) {
            $lat = $r['latitude'] ?? null;
            $lon = $r['longitude'] ?? null;
            if ($lat !== null && $lon !== null && $centerLat !== null && $centerLong !== null) {
                $results[$i]['distance_from_center_km'] = round(
                    $this->haversineKm((float) $lat, (float) $lon, $centerLat, $centerLong),
                    1
                );
            }
        }

        return [
            'region'       => $regionInfo,
            'total_hotels' => $totalHotels,
            'hotels'       => $results,
        ];
    }

    /**
     * @param  array<int, mixed>  $rates
     */
    private function extractFirstRate(array $rates, string $defaultCurrency): ?FirstRateDTO
    {
        $cheapest = null;
        $cheapestAmount = null;

        foreach ($rates as $rate) {
            $opts = $rate['payment_options'] ?? [];
            $types = is_array($opts) && isset($opts['payment_types']) ? $opts['payment_types'] : $opts;
            if (!is_array($types)) {
                continue;
            }
            foreach ($types as $pt) {
                if (!is_array($pt) || !isset($pt['show_amount'])) {
                    continue;
                }
                $amt = (float) $pt['show_amount'];
                $cancelPen = $pt['cancellation_penalties'] ?? $rate['cancellation_penalties'] ?? [];
                $freeCancel = is_array($cancelPen) ? ($cancelPen['free_cancellation_before'] ?? null) : null;
                $ptype = $pt['type'] ?? null;
                $paymentTypeLabel = match ($ptype) {
                    'hotel'   => 'pay_on_site',
                    'deposit' => 'pay_deposit',
                    'now'     => 'pay_now',
                    default   => null,
                };
                if ($cheapestAmount === null || $amt < $cheapestAmount) {
                    $cheapestAmount = $amt;
                    $mealData = $rate['meal_data'] ?? [];
                    $mealData = is_array($mealData) ? $mealData : [];
                    $roomData = $rate['room_data_trans'] ?? [];
                    $cheapest = [
                        'amount'   => $amt,
                        'currency' => $pt['show_currency_code'] ?? $defaultCurrency,
                        'room_name' => $rate['room_name'] ?? null,
                        'bedding_type' => (is_array($roomData) ? ($roomData['bedding_type'] ?? null) : null),
                        'allotment' => isset($rate['allotment']) ? (int) $rate['allotment'] : null,
                        'has_breakfast' => $mealData['has_breakfast'] ?? null,
                        'free_cancellation_before' => $freeCancel,
                        'payment_type' => $paymentTypeLabel,
                        'match_hash' => $rate['match_hash'] ?? null,
                        'search_hash' => $rate['search_hash'] ?? null,
                    ];
                }
            }
        }

        if ($cheapest === null) {
            return null;
        }

        return new FirstRateDTO(
            amount: $cheapest['amount'],
            currency: $cheapest['currency'],
            roomName: $cheapest['room_name'],
            beddingType: $cheapest['bedding_type'],
            allotment: $cheapest['allotment'],
            hasBreakfast: $cheapest['has_breakfast'],
            freeCancellationBefore: $cheapest['free_cancellation_before'],
            paymentType: $cheapest['payment_type'],
            matchHash: $cheapest['match_hash'],
            searchHash: $cheapest['search_hash'],
        );
    }

    /**
     * @param  array<int, mixed>  $rates
     * @return array{amount: ?float, currency: string}
     */
    private function extractMinPrice(array $rates): array
    {
        $min = null;
        $currency = 'USD';

        foreach ($rates as $rate) {
            $opts = $rate['payment_options'] ?? [];
            $types = is_array($opts) && isset($opts['payment_types']) ? $opts['payment_types'] : $opts;
            if (!is_array($types)) {
                continue;
            }
            foreach ($types as $pt) {
                if (!is_array($pt)) {
                    continue;
                }
                $amt = isset($pt['show_amount']) ? (float) $pt['show_amount'] : null;
                if ($amt !== null && ($min === null || $amt < $min)) {
                    $min = $amt;
                    $currency = $pt['show_currency_code'] ?? $currency;
                }
            }
        }

        return ['amount' => $min, 'currency' => $currency];
    }

    private function scoreToQualitative(float $score): string
    {
        return match (true) {
            $score >= 9.0 => 'Excellent',
            $score >= 8.0 => 'Very good',
            $score >= 7.0 => 'Good',
            $score >= 6.0 => 'Pleasant',
            $score >= 5.0 => 'Acceptable',
            default      => 'Below average',
        };
    }

    /**
     * Prefer region center; if missing, use the centroid of hotels in this result so distances still work.
     *
     * @param  array<int, array<string, mixed>>  $hotelRows
     * @return array{0: ?float, 1: ?float}
     */
    private function resolveSearchCenter(?float $regLat, ?float $regLong, array $hotelRows): array
    {
        if ($regLat !== null && $regLong !== null) {
            return [$regLat, $regLong];
        }

        if ($hotelRows === []) {
            return [null, null];
        }

        $lats = [];
        $lngs = [];
        foreach ($hotelRows as $r) {
            $lat = $r['latitude'] ?? null;
            $lon = $r['longitude'] ?? null;
            if ($lat !== null && $lon !== null && is_numeric($lat) && is_numeric($lon)) {
                $lats[] = (float) $lat;
                $lngs[] = (float) $lon;
            }
        }

        if ($lats === []) {
            return [null, null];
        }

        return [array_sum($lats) / count($lats), array_sum($lngs) / count($lngs)];
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $r * $c;
    }
}
