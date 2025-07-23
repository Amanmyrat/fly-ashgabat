<?php

namespace App\Services\XMLAgency\RequestBuilder;

class PreBookRequestBuilder
{
    private array $validatedData;

    public function __construct(array $validatedData)
    {
        $this->validatedData = $validatedData;
    }

    public function build(): array
    {
        return [
            'AeroPrebook' => [
                'credentials' => $this->buildCredentials(),
                'aeroPrebookParams' => $this->buildAeroBookParams(),
            ]
        ];
    }

    private function buildCredentials(): array
    {
        return [
            'ApiLogin' => config('xmlagency.credentials.login'),
            'ApiPassword' => config('xmlagency.credentials.password'),
            'AuthExtendedData' => null,
            'Currency' => config('xmlagency.currency', 'EUR'),
            'DeviceId' => config('xmlagency.device_id', 'web'),
            'Language' => strtoupper(app()->getLocale()),
            'TokenGuid' => config('xmlagency.token_guid'),
        ];
    }

    private function buildAeroBookParams(): array
    {
        return [
            'OfferCode' => $this->validatedData['offer_code'],
            'SearchGuid' => $this->validatedData['search_guid'],
        ];
    }
}
