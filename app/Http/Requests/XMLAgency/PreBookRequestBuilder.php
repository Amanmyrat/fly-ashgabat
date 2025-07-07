<?php

namespace App\Http\Requests\XMLAgency;

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
            'TokenGuid' => '00000000-0000-0000-0000-000000000000',
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
