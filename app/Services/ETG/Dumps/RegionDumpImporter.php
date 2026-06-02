<?php

namespace App\Services\ETG\Dumps;

use Carbon\Carbon;

class RegionDumpImporter extends AbstractDumpImporter
{
    public const SUPPORTED_LANGUAGES = ['en'];
    public const BASE_LANGUAGE       = 'en';

    public function getApiEndpoint(): string
    {
        return '/api/b2b/v3/hotel/region/dump/';
    }

    public function getTable(): string
    {
        return 'regions';
    }

    public function getSyncPrefix(): string
    {
        return 'region_dump';
    }

    public function getStorageDir(): string
    {
        return 'etg/regions';
    }

    public function getPrimaryKey(): string
    {
        return 'id';
    }

    protected function getRequestBody(string $language): object|array
    {
        return ['language' => 'en'];
    }

    protected function mapBaseRecord(array $data, Carbon $now): ?array
    {
        if (empty($data['id'])) {
            return null;
        }

        $names       = is_array($data['name']) ? $data['name'] : [];
        $countryName = is_array($data['country_name'] ?? null) ? $data['country_name'] : [];
        $center      = is_array($data['center'] ?? null) ? $data['center'] : [];

        return [
            'id'              => (int) $data['id'],
            'name_en'         => self::str($names['en'] ?? '', 500),
            'name_ru'         => self::str($names['ru'] ?? null, 500),
            'country_name_en' => self::str($countryName['en'] ?? null, 500),
            'country_name_ru' => self::str($countryName['ru'] ?? null, 500),
            'type'            => self::str($data['type'] ?? '', 255),
            'country_code'    => isset($data['country_code']) ? substr((string) $data['country_code'], 0, 2) : null,
            'latitude'        => isset($center['latitude'])  ? (float) $center['latitude']  : null,
            'longitude'       => isset($center['longitude']) ? (float) $center['longitude'] : null,
            'iata'            => self::str($data['iata'] ?? null, 10),
            'created_at'      => $now,
            'updated_at'      => $now,
        ];
    }

    protected function mapTranslationFields(array $data, string $language): ?array
    {
        return null;
    }
}
