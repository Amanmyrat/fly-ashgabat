<?php

namespace App\Services;

/**
 * Facets are computed from the full search result (before user filters).
 * Filtering is applied to that list before pagination.
 */
class HotelSearchFacetAndFilterService
{
    /** @var array<string, string> */
    private const KIND_LABELS = [
        'hotel'     => 'Hotel',
        'hostel'    => 'Hostel',
        'apartment' => 'Apartment',
        'motel'     => 'Motel',
        'resort'    => 'Resort',
        'villa'     => 'Villa',
        'guesthouse' => 'Guest house',
        'bnb'       => 'B&B',
        'camping'   => 'Camping',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $hotels
     * @return array<string, mixed>
     */
    public function buildFacets(array $hotels): array
    {
        if ($hotels === []) {
            return $this->emptyFacets();
        }

        $priceMin      = null;
        $priceMax      = null;
        $priceCurrency = 'USD';
        $distMin       = null;
        $distMax       = null;
        $kindCounts   = [];
        $starCounts   = [];
        $amenityCounts = [];
        $ratings      = [];
        $freeCancellationCount = 0;

        foreach ($hotels as $h) {
            $p = $h['price_from'] ?? null;
            if ($p !== null && is_numeric($p)) {
                $pf = (float) $p;
                if ($priceMin === null || $pf < $priceMin) {
                    $priceMin = $pf;
                }
                if ($priceMax === null || $pf > $priceMax) {
                    $priceMax      = $pf;
                    $priceCurrency = is_string($h['currency'] ?? null) ? $h['currency'] : 'USD';
                }
            }

            $d = $h['distance_from_center_km'] ?? null;
            if ($d !== null && is_numeric($d)) {
                $df = (float) $d;
                if ($distMin === null || $df < $distMin) {
                    $distMin = $df;
                }
                if ($distMax === null || $df > $distMax) {
                    $distMax = $df;
                }
            }

            $kind = $h['kind'] ?? null;
            if (is_string($kind) && $kind !== '') {
                $k = strtolower($kind);
                $kindCounts[$k] = ($kindCounts[$k] ?? 0) + 1;
            }

            $stars = $h['stars'] ?? null;
            if ($stars !== null && is_numeric($stars)) {
                $si = (int) $stars;
                $starCounts[$si] = ($starCounts[$si] ?? 0) + 1;
            }

            $sf = $h['serp_filters'] ?? [];
            if (is_array($sf)) {
                foreach ($sf as $code) {
                    if (!is_string($code) || $code === '') {
                        continue;
                    }
                    $amenityCounts[$code] = ($amenityCounts[$code] ?? 0) + 1;
                }
            }

            $r = $h['avg_rating'] ?? $h['score'] ?? null;
            if ($r !== null && is_numeric($r)) {
                $ratings[] = (float) $r;
            }

            $fr = $h['first_rate'] ?? [];
            if (is_array($fr)) {
                $hasFree = isset($fr['free_cancellation_before']) && !empty($fr['free_cancellation_before']);
                if ($hasFree) {
                    $freeCancellationCount++;
                }
            }
        }

        $propertyTypes = [];
        foreach ($kindCounts as $value => $count) {
            if ($count <= 0) {
                continue;
            }
            $propertyTypes[] = [
                'value' => $value,
                'name'  => self::KIND_LABELS[$value] ?? $this->humanize($value),
                'count' => $count,
            ];
        }
        usort($propertyTypes, fn (array $a, array $b) => $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']));

        $amenities = [];
        foreach ($amenityCounts as $value => $count) {
            if ($count <= 0) {
                continue;
            }
            $amenities[] = [
                'value' => $value,
                'name'  => $this->humanize($value),
                'count' => $count,
            ];
        }
        usort($amenities, fn (array $a, array $b) => $b['count'] <=> $a['count'] ?: strcmp($a['value'], $b['value']));

        krsort($starCounts, SORT_NUMERIC);
        $starRating = [];
        foreach ($starCounts as $stars => $count) {
            if ($count > 0) {
                $starRating[] = ['stars' => (int) $stars, 'count' => $count];
            }
        }

        $ratingMin = $ratings !== [] ? min($ratings) : null;
        $ratingMax = $ratings !== [] ? max($ratings) : null;
        $noRating  = count($hotels) - count($ratings);

        $buckets = $this->ratingBuckets($ratings);

        return [
            'price' => [
                'min'      => $priceMin !== null ? round($priceMin, 2) : null,
                'max'      => $priceMax !== null ? round($priceMax, 2) : null,
                'currency' => $priceCurrency,
            ],
            'property_types' => $propertyTypes,
            'distance_from_center_km' => [
                'min' => $distMin !== null ? round($distMin, 1) : null,
                'max' => $distMax !== null ? round($distMax, 1) : null,
            ],
            'amenities' => $amenities,
            'star_rating' => $starRating,
            'review_score' => [
                'min'               => $ratingMin !== null ? round($ratingMin, 2) : null,
                'max'               => $ratingMax !== null ? round($ratingMax, 2) : null,
                'no_rating_count'   => $noRating,
                'buckets'           => $buckets,
            ],
            'free_cancellation' => [
                'available' => $freeCancellationCount > 0,
                'count' => $freeCancellationCount,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $hotels
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function applyFilters(array $hotels, array $filters): array
    {
        if ($filters === []) {
            return $hotels;
        }

        $minPrice       = $filters['min_price'] ?? null;
        $maxPrice       = $filters['max_price'] ?? null;
        $minDist        = $filters['min_distance_km'] ?? null;
        $propertyTypes  = isset($filters['property_types']) && is_array($filters['property_types'])
            ? array_map('strtolower', array_filter($filters['property_types'], 'is_string'))
            : [];
        $maxDist        = $filters['max_distance_km'] ?? null;
        $amenities      = isset($filters['amenities']) && is_array($filters['amenities'])
            ? array_values(array_filter($filters['amenities'], 'is_string'))
            : [];
        $starsFilter    = isset($filters['stars']) && is_array($filters['stars'])
            ? array_map('intval', $filters['stars'])
            : [];
        $minReviewScore = $filters['min_review_score'] ?? null;
        $maxReviewScore = $filters['max_review_score'] ?? null;
        $wantBreakfast  = array_key_exists('has_breakfast', $filters) ? $filters['has_breakfast'] : null;
        $paymentTypes   = isset($filters['payment_types']) && is_array($filters['payment_types'])
            ? array_values(array_filter($filters['payment_types'], 'is_string'))
            : [];
        $wantFreeCancellation = array_key_exists('has_free_cancellation', $filters) ? $filters['has_free_cancellation'] : null;

        $out = [];
        foreach ($hotels as $h) {
            if (is_numeric($minPrice)) {
                $p = $h['price_from'] ?? null;
                if ($p === null || (float) $p < (float) $minPrice) {
                    continue;
                }
            }

            if (is_numeric($maxPrice)) {
                $p = $h['price_from'] ?? null;
                if ($p === null || (float) $p > (float) $maxPrice) {
                    continue;
                }
            }

            if ($propertyTypes !== []) {
                $k = strtolower((string) ($h['kind'] ?? ''));
                if ($k === '' || !in_array($k, $propertyTypes, true)) {
                    continue;
                }
            }

            if (is_numeric($minDist)) {
                $d = $h['distance_from_center_km'] ?? null;
                if ($d === null || (float) $d < (float) $minDist) {
                    continue;
                }
            }

            if (is_numeric($maxDist)) {
                $d = $h['distance_from_center_km'] ?? null;
                if ($d === null || (float) $d > (float) $maxDist) {
                    continue;
                }
            }

            if ($amenities !== []) {
                $sf = $h['serp_filters'] ?? [];
                if (!is_array($sf)) {
                    continue;
                }
                $set = [];
                foreach ($sf as $code) {
                    if (is_string($code) && $code !== '') {
                        $set[$code] = true;
                    }
                }
                $ok = true;
                foreach ($amenities as $a) {
                    if (!isset($set[$a])) {
                        $ok = false;
                        break;
                    }
                }
                if (!$ok) {
                    continue;
                }
            }

            if ($starsFilter !== []) {
                $s = $h['stars'] ?? null;
                if ($s === null || !in_array((int) $s, $starsFilter, true)) {
                    continue;
                }
            }

            if (is_numeric($minReviewScore)) {
                $r = $h['avg_rating'] ?? $h['score'] ?? null;
                if ($r === null || (float) $r < (float) $minReviewScore) {
                    continue;
                }
            }

            if (is_numeric($maxReviewScore)) {
                $r = $h['avg_rating'] ?? $h['score'] ?? null;
                if ($r === null || (float) $r > (float) $maxReviewScore) {
                    continue;
                }
            }

            if ($wantBreakfast === true) {
                $fr = $h['first_rate'] ?? [];
                if (!is_array($fr) || ($fr['has_breakfast'] ?? null) !== true) {
                    continue;
                }
            }

            if ($paymentTypes !== []) {
                $fr = $h['first_rate'] ?? [];
                $pt = is_array($fr) ? ($fr['payment_type'] ?? null) : null;
                if (!is_string($pt) || !in_array($pt, $paymentTypes, true)) {
                    continue;
                }
            }

            if ($wantFreeCancellation === true) {
                $fr = $h['first_rate'] ?? [];
                $hasFree = is_array($fr) && !empty($fr['free_cancellation_before']);
                if (!$hasFree) {
                    continue;
                }
            }

            $out[] = $h;
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $hotels
     * @param string|null $sortBy
     * @return array<int, array<string, mixed>>
     */
    public function applySorting(array $hotels, ?string $sortBy): array
    {
        if (!$sortBy) {
            return $hotels;
        }

        usort($hotels, function ($a, $b) use ($sortBy) {
            return match ($sortBy) {
                'price_asc' => ($a['price_from'] ?? INF) <=> ($b['price_from'] ?? INF),
                'price_desc' => ($b['price_from'] ?? -INF) <=> ($a['price_from'] ?? -INF),

                'rating_desc' => ($b['avg_rating'] ?? $b['score'] ?? -INF)
                    <=> ($a['avg_rating'] ?? $a['score'] ?? -INF),

                'rating_asc' => ($a['avg_rating'] ?? $a['score'] ?? INF)
                    <=> ($b['avg_rating'] ?? $b['score'] ?? INF),

                'distance_asc' => ($a['distance_from_center_km'] ?? INF)
                    <=> ($b['distance_from_center_km'] ?? INF),

                'distance_desc' => ($b['distance_from_center_km'] ?? -INF)
                    <=> ($a['distance_from_center_km'] ?? -INF),

                'stars_desc' => ($b['stars'] ?? -INF) <=> ($a['stars'] ?? -INF),
                'stars_asc' => ($a['stars'] ?? INF) <=> ($b['stars'] ?? INF),

                default => 0,
            };
        });

        return $hotels;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFacets(): array
    {
        return [
            'price' => ['min' => null, 'max' => null, 'currency' => 'USD'],
            'property_types' => [],
            'distance_from_center_km' => ['min' => null, 'max' => null],
            'amenities' => [],
            'star_rating' => [],
            'review_score' => [
                'min'             => null,
                'max'             => null,
                'no_rating_count' => 0,
                'buckets'         => [],
            ],
            'free_cancellation' => [
                'available' => false,
                'count' => 0,
            ],
        ];
    }

    /**
     * Non-overlapping buckets: [9,10], [8,9), [7,8), … [0,5).
     *
     * @param  array<int, float>  $ratings
     * @return array<int, array{from: float, to: float, label: string, count: int}>
     */
    private function ratingBuckets(array $ratings): array
    {
        if ($ratings === []) {
            return [];
        }

        /** @var array<int, array{0: callable(float): bool, 1: float, 2: float, 3: string}> $definitions */
        $definitions = [
            [fn (float $r) => $r >= 9.0 && $r <= 10.0, 9.0, 10.0, '9–10'],
            [fn (float $r) => $r >= 8.0 && $r < 9.0, 8.0, 9.0, '8–9'],
            [fn (float $r) => $r >= 7.0 && $r < 8.0, 7.0, 8.0, '7–8'],
            [fn (float $r) => $r >= 6.0 && $r < 7.0, 6.0, 7.0, '6–7'],
            [fn (float $r) => $r >= 5.0 && $r < 6.0, 5.0, 6.0, '5–6'],
            [fn (float $r) => $r >= 0.0 && $r < 5.0, 0.0, 5.0, '0–5'],
        ];

        $buckets = [];
        foreach ($definitions as [$match, $from, $to, $label]) {
            $c = 0;
            foreach ($ratings as $r) {
                if ($match($r)) {
                    $c++;
                }
            }
            if ($c > 0) {
                $buckets[] = [
                    'from'  => $from,
                    'to'    => $to,
                    'label' => $label,
                    'count' => $c,
                ];
            }
        }

        return $buckets;
    }

    private function humanize(string $value): string
    {
        $v = str_replace('_', ' ', $value);

        return mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
    }
}
