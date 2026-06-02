<?php

namespace App\Services\ETG\Dumps;

use Carbon\Carbon;

class HotelDumpImporter extends AbstractDumpImporter
{
    public function getApiEndpoint(): string
    {
        return '/api/b2b/v3/hotel/info/dump/';
    }

    public function getTable(): string
    {
        return 'hotels';
    }

    public function getSyncPrefix(): string
    {
        return 'hotel_dump';
    }

    public function getStorageDir(): string
    {
        return 'etg/hotel';
    }

    public function getPrimaryKey(): string
    {
        return 'hid';
    }

    protected function getRequestBody(string $language): object|array
    {
        return ['language' => $language];
    }

    protected function mapBaseRecord(array $data, Carbon $now): ?array
    {
        if (empty($data['hid'])) {
            return null;
        }

        if (empty($data['images']) || empty($data['serp_filters'])) {
            return null;
        }

        $region = $data['region'] ?? [];

        return [
            'hid'            => (int) $data['hid'],
            'etg_id'         => self::str($data['id'] ?? null, 50),
            'name_en'        => self::str($data['name'] ?? '', 500),
            'region_id'      => (int) ($region['id'] ?? 0),
            'country_code'   => isset($region['country_code']) ? substr((string) $region['country_code'], 0, 2) : null,
            'latitude'       => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude'      => isset($data['longitude']) ? (float) $data['longitude'] : null,
            'star_rating'    => isset($data['star_rating']) ? (int) $data['star_rating'] : null,
            'kind'           => self::str($data['kind'] ?? null, 50),
            'address_en'     => self::str($data['address'] ?? null, 1000),
            'check_in_time'  => self::str($data['check_in_time'] ?? null, 8),
            'check_out_time' => self::str($data['check_out_time'] ?? null, 8),
            'images'         => isset($data['images']) ? json_encode($data['images']) : null,
            'serp_filters'   => isset($data['serp_filters']) ? json_encode($data['serp_filters']) : null,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
    }

    protected function mapTranslationFields(array $data, string $language): ?array
    {
        if (empty($data['hid'])) {
            return null;
        }

        return [
            'pk'                    => (int) $data['hid'],
            "name_{$language}"      => self::str($data['name'] ?? '', 500),
            "address_{$language}"   => self::str($data['address'] ?? null, 1000),
        ];
    }
}
