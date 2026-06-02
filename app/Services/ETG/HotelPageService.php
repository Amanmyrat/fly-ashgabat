<?php

namespace App\Services\ETG;

use App\DTO\HotelPageResultDTO;
use App\DTO\HotelRoomPlanDTO;
use App\DTO\RegionInfoDTO;
use App\Models\Hotel;
use App\Support\EtgRoomFeaturesMapper;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class HotelPageService
{
    private const HP_ENDPOINT   = 'api/b2b/v3/search/hp/';
    private const INFO_ENDPOINT = 'api/b2b/v3/hotel/info/';

    public function __construct(private readonly EtgClient $client) {}

    /**
     * @param  array{checkin: string, checkout: string, language: string, currency: string, guests: array}  $params
     */
    public function getHotelPage(int $hid, array $params): ?HotelPageResultDTO
    {
        $local = Hotel::with(['region', 'reviewStats'])->find($hid);
        $etgId = $local?->etg_id;

        $lang     = $params['language'] ?? 'en';
        $currency = 'USD';

        $hpBody = array_merge([
            'checkin'   => $params['checkin'],
            'checkout'  => $params['checkout'],
            'language'  => $lang,
            'currency'  => $currency,
            'guests'    => array_map(fn (array $g) => [
                'adults'   => $g['adults'],
                'children' => $g['child_ages'] ?? $g['children'] ?? [],
            ], $params['guests'] ?? []),
        ], $etgId === null ? ['hid' => $hid] : ['id' => $etgId]);

        $infoBody = $etgId === null
            ? ['hid' => $hid, 'language' => $lang]
            : ['id' => $etgId, 'language' => $lang];

        $infoHotel = $this->fetchHotelInfoSafe($infoBody);

        $hpResponse = $this->client->post(self::HP_ENDPOINT, $hpBody);
        $hpData     = $hpResponse['data'] ?? $hpResponse;
        $etgHotel   = ($hpData['hotels'] ?? [])[0] ?? [];

        if ($infoHotel === [] && $etgHotel === []) {
            return null;
        }

        $nights = $this->stayNights($params['checkin'], $params['checkout']);

        $roomGroups = is_array($infoHotel['room_groups'] ?? null)
            ? $infoHotel['room_groups']
            : [];

        $hotelImages = $this->extractHotelImages($infoHotel, $local);

        $rates = is_array($etgHotel['rates'] ?? null)
            ? $etgHotel['rates']
            : [];

        $rooms = $this->buildGroupedRooms(
            $rates,
            $roomGroups,
            $nights,
            $currency,
            $hotelImages,
        );

        if ($rooms === []) {
            $rooms = null;
        }

        return $this->buildResultDto(
            $hid,
            $local,
            $infoHotel,
            $etgHotel,
            $rooms,
            $params
        );
    }

    /**
     * @param  array<string, mixed>  $infoBody
     * @return array<string, mixed>
     */
    private function fetchHotelInfoSafe(array $infoBody): array
    {
        try {
            $response = $this->client->post(self::INFO_ENDPOINT, $infoBody);

            return $this->extractHotelInfoHotel($response);
        } catch (RequestException $e) {
            Log::channel('etg')->warning('ETG hotel/info failed; using DB fallback for static fields.', [
                'body' => $infoBody,
                'err'  => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::channel('etg')->warning('ETG hotel/info error.', ['err' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function extractHotelInfoHotel(array $response): array
    {
        $data = $response['data'] ?? $response;
        if (!is_array($data)) {
            return [];
        }
        if (isset($data['hotels'][0]) && is_array($data['hotels'][0])) {
            return $data['hotels'][0];
        }
        if (isset($data['hotel']) && is_array($data['hotel'])) {
            return $data['hotel'];
        }
        if (isset($data['hid']) || isset($data['id'])) {
            return $data;
        }

        return $data;
    }

    private function stayNights(string $checkin, string $checkout): int
    {
        try {
            $n = Carbon::parse($checkin)->diffInDays(Carbon::parse($checkout));

            return max(1, (int) $n);
        } catch (Throwable) {
            return 1;
        }
    }

    /**
     * Group rates by room type, build plans per rate, sort plans by price and mark cheapest.
     *
     * @param  array<int, mixed>   $rates
     * @param  array<int, mixed>   $roomGroups
     * @param  array<int, string>  $hotelImages  Fallback images when room has none
     * @return array<int, array{
     * room_name: string,
     * size_m2: float|null,
     * room_features: array<string, mixed>,
     * images: array<int, string>,
     * amenities: array<int, mixed>,
     * plans: HotelRoomPlanDTO[]
     * }>
     */
    private function buildGroupedRooms(
        array $rates,
        array $roomGroups,
        int $nights,
        string $defaultCurrency,
        array $hotelImages = [],
    ): array {
        $grouped = [];

        foreach ($rates as $rate) {
            if (!is_array($rate)) {
                continue;
            }

            $roomTrans = is_array($rate['room_data_trans'] ?? null) ? $rate['room_data_trans'] : [];
            $mainType  = isset($roomTrans['main_room_type']) ? trim((string) $roomTrans['main_room_type']) : '';
            $rateName  = isset($rate['room_name']) ? trim((string) $rate['room_name']) : '';
            $roomKey   = $mainType !== '' ? $mainType : ($rateName !== '' ? $rateName : 'Room');

            if (!isset($grouped[$roomKey])) {
                $group = $this->findRoomGroupForRate($rate, $roomGroups) ?? [];
                $grouped[$roomKey] = [
                    'room_name' => $roomKey,
                    'size_m2' => isset($group['size'])
                        ? (float) $group['size']
                        : null,
                    'room_features' => EtgRoomFeaturesMapper::map(
                        is_array($group['rg_ext'] ?? null)
                            ? $group['rg_ext']
                            : []
                    ),
                    'images' => $this->extractRoomGroupImages($group, $hotelImages),
                    'amenities' => $this->extractRoomAmenities($group) ?? [],
                    'raw_plans' => [],
                ];
            }

            $grouped[$roomKey]['raw_plans'][] = $this->buildRawPlan($rate, $nights, $defaultCurrency);
        }

        $rooms = [];
        foreach ($grouped as $data) {
            $rawPlans = $data['raw_plans'];
            usort($rawPlans, static fn ($a, $b) =>
                ($a['price_total'] ?? PHP_FLOAT_MAX) <=> ($b['price_total'] ?? PHP_FLOAT_MAX)
            );

            $plans = [];
            foreach ($rawPlans as $i => $raw) {
                $plans[] = new HotelRoomPlanDTO(
                    priceTotal:            $raw['price_total'],
                    pricePerNight:         $raw['price_per_night'],
                    currency:              $raw['currency'],
                    availability:          $raw['availability'],
                    mealType:              $raw['meal_type'],
                    hasBreakfast:          $raw['has_breakfast'],
                    cancellationFreeUntil: $raw['cancellation_free_until'],
                    cancellationPolicy:    $raw['cancellation_policy'],
                    paymentType:           $raw['payment_type'],
                    taxes:                 $raw['taxes'],
                    bookHash:              $raw['book_hash'],
                    searchHash:            $raw['search_hash'],
                    isCheapest:            $i === 0,
                    roomData:              $raw['room_data'] ?? null,
                    serpFilters:           $raw['serp_filters'] ?? null,
                    amenitiesData:         $raw['amenities_data'] ?? null,
                    noShow:                $raw['no_show'] ?? null,
                );
            }

            $rooms[] = [
                'room_name'     => $data['room_name'],
                'size_m2'       => $data['size_m2'],
                'room_features' => $data['room_features'],
                'images'        => $data['images'],
                'amenities'     => $data['amenities'],
                'plans'         => $plans,
            ];
        }

        return $rooms;
    }

    /**
     * Extract all price/meal/cancellation/tax data from a single rate into a plain array.
     *
     * @param  array<string, mixed>  $rate
     * @return array<string, mixed>
     */
    private function buildRawPlan(array $rate, int $nights, string $defaultCurrency): array
    {
        $opts    = is_array($rate['payment_options'] ?? null) ? $rate['payment_options'] : [];
        $types   = is_array($opts['payment_types'] ?? null) ? $opts['payment_types'] : [];
        $payment = is_array($types[0] ?? null) ? $types[0] : [];

        $amount   = isset($payment['amount']) ? (float) $payment['amount'] : null;
        $currency = isset($payment['currency_code']) ? (string) $payment['currency_code'] : $defaultCurrency;
        $payType  = isset($payment['type']) ? (string) $payment['type'] : 'deposit';

        $allotment = isset($rate['allotment'])
            ? (int) $rate['allotment']
            : null;

        $dailyPrices = is_array($rate['daily_prices'] ?? null) ? array_map('floatval', $rate['daily_prices']) : [];

        if ($amount === null && !empty($dailyPrices)) {
            $amount = (float) array_sum($dailyPrices);
        }

        $perNight = ($amount !== null && $nights > 0) ? round($amount / $nights, 2) : null;
        if (!empty($dailyPrices)) {
            $perNight = round(array_sum($dailyPrices) / count($dailyPrices), 2);
        }

        $mealData     = is_array($rate['meal_data'] ?? null) ? $rate['meal_data'] : [];
        $mealType     = isset($mealData['value']) ? (string) $mealData['value'] : (isset($rate['meal']) ? (string) $rate['meal'] : null);
        $hasBreakfast = isset($mealData['has_breakfast']) ? (bool) $mealData['has_breakfast'] : null;

        // Cancellation: prefer within payment type, fall back to rate level
        $cancelPen = is_array($payment['cancellation_penalties'] ?? null)
            ? $payment['cancellation_penalties']
            : (is_array($rate['cancellation_penalties'] ?? null) ? $rate['cancellation_penalties'] : []);

        $freeUntil = isset($cancelPen['free_cancellation_before'])
            ? (string) $cancelPen['free_cancellation_before']
            : null;

        // Taxes: prefer within payment type, fall back to rate level
        $taxData = is_array($payment['tax_data'] ?? null)
            ? $payment['tax_data']
            : (is_array($rate['tax_data'] ?? null) ? $rate['tax_data'] : null);
        $taxes = is_array($taxData['taxes'] ?? null) ? $taxData['taxes'] : null;

        $roomDataTrans = is_array($rate['room_data_trans'] ?? null) ? $rate['room_data_trans'] : null;
        $serpFilters   = is_array($rate['serp_filters'] ?? null) ? $rate['serp_filters'] : null;
        $amenitiesData = is_array($rate['amenities_data'] ?? null) ? $rate['amenities_data'] : null;
        $noShow        = is_array($rate['no_show'] ?? null) ? $rate['no_show'] : null;

        return [
            'price_total'             => $amount,
            'price_per_night'         => $perNight,
            'currency'                => $currency,
            'availability' => $this->mapAvailability($allotment),
            'meal_type'               => $mealType,
            'has_breakfast'           => $hasBreakfast,
            'room_data'               => $roomDataTrans,
            'serp_filters'            => $serpFilters,
            'amenities_data'          => $amenitiesData,
            'no_show'                 => $noShow,
            'cancellation_free_until' => $freeUntil,
            'cancellation_policy'     => $cancelPen['policies'],
            'payment_type'            => $payType,
            'taxes'                   => $taxes,
            'book_hash'               => isset($rate['book_hash']) ? (string) $rate['book_hash'] : null,
            'search_hash'             => isset($rate['match_hash'])
                ? (string) $rate['match_hash']
                : (isset($rate['search_hash']) ? (string) $rate['search_hash'] : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $rate
     * @param  array<int, mixed>  $roomGroups
     * @return array<string, mixed>|null
     */
    private function findRoomGroupForRate(array $rate, array $roomGroups): ?array
    {
        $roomTrans = is_array($rate['room_data_trans'] ?? null) ? $rate['room_data_trans'] : [];
        $mainType  = isset($roomTrans['main_room_type']) ? trim((string) $roomTrans['main_room_type']) : '';

        foreach ($roomGroups as $g) {
            if (!is_array($g)) {
                continue;
            }
            $ns       = is_array($g['name_struct'] ?? null) ? $g['name_struct'] : [];
            $mainName = isset($ns['main_name']) ? trim((string) $ns['main_name']) : '';
            if ($mainType !== '' && $mainName !== '' && strcasecmp($mainType, $mainName) === 0) {
                return $g;
            }
        }

        $roomName = (string) ($rate['room_name'] ?? '');
        foreach ($roomGroups as $g) {
            if (!is_array($g)) {
                continue;
            }
            $ns       = is_array($g['name_struct'] ?? null) ? $g['name_struct'] : [];
            $mainName = isset($ns['main_name']) ? (string) $ns['main_name'] : '';
            $gName    = $mainName !== '' ? $mainName : (string) ($g['name'] ?? '');
            if ($roomName !== '' && $gName !== '') {
                if (stripos($roomName, $gName) !== false || stripos($gName, $roomName) !== false) {
                    return $g;
                }
            }
        }

        return null;
    }

    /**
     * Images priority: room_group.images → room_group.images_ext[].url → hotel images → []
     *
     * @param  array<string, mixed>  $group
     * @param  array<int, mixed>     $hotelImages
     * @return array<int, string>
     */
    private function extractRoomGroupImages(array $group, array $hotelImages = []): array
    {
        $imgs = $this->normalizeImageList($group['images'] ?? $group['gallery'] ?? []);
        if (!empty($imgs)) {
            return $imgs;
        }

        $imgsExt = $group['images_ext'] ?? [];
        if (is_array($imgsExt) && !empty($imgsExt)) {
            $out = [];
            foreach ($imgsExt as $img) {
                if (is_array($img) && isset($img['url'])) {
                    $out[] = (string) $img['url'];
                } elseif (is_string($img) && $img !== '') {
                    $out[] = $img;
                }
            }
            if (!empty($out)) {
                return $out;
            }
        }

        return $this->normalizeImageList($hotelImages);
    }

    /**
     * @param  mixed  $imgs
     * @return array<int, string>
     */
    private function normalizeImageList(mixed $imgs): array
    {
        if (!is_array($imgs)) {
            return [];
        }
        $out = [];
        foreach ($imgs as $img) {
            if (is_string($img) && $img !== '') {
                $out[] = $img;
            } elseif (is_array($img) && isset($img['url']) && (string) $img['url'] !== '') {
                $out[] = (string) $img['url'];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<int, mixed>|null
     */
    private function extractRoomAmenities(array $group): ?array
    {
        $a = $group['room_amenities'] ?? $group['amenities'] ?? null;
        if (!is_array($a)) {
            return null;
        }

        return $a;
    }

    /**
     * @param  array<string, mixed>  $infoHotel
     * @param  array<string, mixed>  $etgHotel
     * @param  array<int, array{
     *     room_name: string,
     *     size_m2: float|null,
     *     room_features: array<string, mixed>,
     *     images: array<int, string>,
     *     amenities: array<int, mixed>,
     *     plans: HotelRoomPlanDTO[]
     * }>|null  $rooms
     * @param  array{checkin: string, checkout: string, language: string, guests: array}  $params
     */
    private function buildResultDto(
        int $hid,
        ?Hotel $local,
        array $infoHotel,
        array $etgHotel,
        ?array $rooms,
        array $params
    ): HotelPageResultDTO {
        $lang = $params['language'] ?? 'en';

        $infoName = $this->pickLocalizedField($infoHotel['name'] ?? null, $lang);
        $name     = $this->stringFromInfoOrLocal(
            $infoName,
            $local,
            fn () => $lang === 'ru' ? ($local?->name_ru ?? $local?->name_en) : ($local?->name_en ?? $local?->name_ru),
            (string) ($etgHotel['name'] ?? '')
        );

        $infoAddr = $this->pickLocalizedField($infoHotel['address'] ?? null, $lang);
        $address  = $this->stringFromInfoOrLocal(
            $infoAddr,
            $local,
            fn () => $lang === 'ru' ? ($local?->address_ru ?? $local?->address_en) : ($local?->address_en ?? $local?->address_ru),
            null
        );

        $stars = isset($infoHotel['star_rating'])
            ? (int) round((float) $infoHotel['star_rating'])
            : ($local?->star_rating);

        $desc = $infoHotel['description_struct'] ?? null;
        $desc = is_array($desc) ? $desc : null;

        $amenityGroups = $infoHotel['amenity_groups'] ?? null;
        $amenityGroups = is_array($amenityGroups) ? $amenityGroups : null;

        $images = $this->extractHotelImages($infoHotel, $local);

        $lat = isset($infoHotel['latitude']) ? (float) $infoHotel['latitude'] : $local?->latitude;
        $lng = isset($infoHotel['longitude']) ? (float) $infoHotel['longitude'] : $local?->longitude;

        $checkIn  = $infoHotel['check_in_time'] ?? $local?->check_in_time;
        $checkOut = $infoHotel['check_out_time'] ?? $local?->check_out_time;
        $checkIn  = $checkIn !== null ? (string) $checkIn : null;
        $checkOut = $checkOut !== null ? (string) $checkOut : null;

        $kind = $local?->kind ?? ($infoHotel['kind'] ?? null);

        $regionDto = null;
        if ($local?->region) {
            $region      = $local->region;
            $regionName  = $lang === 'ru' ? ($region->name_ru ?? $region->name_en) : ($region->name_en ?? $region->name_ru);
            $countryName = $lang === 'ru' ? ($region->country_name_ru ?? $region->country_name_en) : ($region->country_name_en ?? $region->country_name_ru);
            $regionDto   = new RegionInfoDTO(
                $region->id,
                $regionName,
                $region->type,
                $region->country_code,
                $countryName
            );
        }

        $etgIdStr = $local?->etg_id ?? (isset($etgHotel['id']) ? (string) $etgHotel['id'] : null);

        return new HotelPageResultDTO(
            hotelId: $hid,
            etgId: $etgIdStr,
            name: $name ?? '',
            stars: $stars,
            address: $address,
            descriptionStruct: $desc,
            amenityGroups: $amenityGroups,
            images: $images,
            latitude: $lat,
            longitude: $lng,
            region: $regionDto,
            kind: $kind,
            checkInTime: $checkIn,
            checkOutTime: $checkOut,
            roomOffers: $rooms,
            reviewsCount: $local?->reviewStats?->reviews_count ?? 0,
            avgRating: $local?->reviewStats?->avg_rating,
        );
    }

    /**
     * @return array<int, string>
     */
    private function extractHotelImages(array $infoHotel, ?Hotel $local): array
    {
        if (isset($infoHotel['images']) && is_array($infoHotel['images'])) {
            return array_values($this->normalizeImageList($infoHotel['images']));
        }
        if ($local && is_array($local->images)) {
            return array_values($this->normalizeImageList($local->images));
        }

        return [];
    }

    private function stringFromInfoOrLocal(mixed $infoVal, ?Hotel $local, callable $localFallback, ?string $etgFallback): ?string
    {
        if (is_string($infoVal) && $infoVal !== '') {
            return $infoVal;
        }
        if ($local !== null) {
            $v = $localFallback();
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return $etgFallback;
    }

    /**
     * ETG often returns a plain string or a map of locale → string.
     */
    private function pickLocalizedField(mixed $value, string $lang): ?string
    {
        if (is_string($value)) {
            return $value !== '' ? $value : null;
        }
        if (!is_array($value)) {
            return null;
        }
        $fallback = $value['en'] ?? $value['ru'] ?? null;
        $picked   = $value[$lang] ?? $fallback;
        if (is_string($picked) && $picked !== '') {
            return $picked;
        }
        foreach ($value as $v) {
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return null;
    }

    private function mapAvailability(?int $allotment): array
    {
        return [
            'rooms_left' => $allotment,

            'level' => match (true) {
                $allotment === null => 'unknown',
                $allotment <= 1 => 'last_room',
                $allotment <= 3 => 'limited',
                default => 'available',
            },
        ];
    }
}
