<?php

namespace App\Services\XMLAgency\RequestBuilder;

use Carbon\Carbon;

class AeroBookRequestBuilder
{
    private array $validatedData;

    public function __construct(array $validatedData)
    {
        $this->validatedData = $validatedData;
    }

    public function build(): array
    {
        return [
            'AeroBook' => [
                'credentials' => $this->buildCredentials(),
                'aeroBookParams' => $this->buildAeroBookParams(),
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
        $contactDetails = $this->validatedData['contact_details'];

        $selectedTariff = null;

        if (isset($this->validatedData['selected_tariff'])){
            $selectedTariff = [
                'int' => $this->validatedData['selected_tariff']
            ];
        }

        return [
            'ClientReference' => $this->generateClientReference(),
            'CustomerFIO' => null,
            'Email' => $contactDetails['email'],
            'ExtendedParams' => null,
            'Marker' => null,
            'OfferCode' => $this->validatedData['offer_code'],
            'Partner' => null,
            'PaxList' => $this->buildPaxList(),
            'Phone' => $contactDetails['phone']['code'].$contactDetails['phone']['number'],
//            'Phone' => '+79871234567',
            'SearchGuid' => $this->validatedData['search_guid'],
            'SelectedServices' => null,
            'SelectedTariffs' => $selectedTariff,
            'Utm' => null,
        ];
    }

    private function buildPaxList(): array
    {
        $paxList = ['PaxData' => []];

        foreach ($this->validatedData['travellers'] as $traveller) {
            $paxList['PaxData'][] = [
                'AgeType' => $this->getAgeType($traveller['birthdate']),
                'BirthDay' => $this->formatBirthDate($traveller['birthdate']),
                'BirthISO' => strtoupper($traveller['nationality']),
                'Document' => $traveller['passport_number'],
                'GenderType' => $this->formatGender($traveller['gender']),
                'MiddleName' => $traveller['middlename'] ?? null,
                'Name' => $traveller['firstname'],
                'Surname' => $traveller['lastname'],
            ];
        }

        return $paxList;
    }

    private function getAgeType(string $birthdate): string
    {
        $age = Carbon::parse($birthdate)->age;

        if ($age < 2) {
            return 'Infant';
        } elseif ($age < 12) {
            return 'Child';
        } else {
            return 'Adult';
        }
    }

    private function formatBirthDate(string $birthdate): string
    {
        // Convert from YYYY-MM-DD to DD.MM.YYYY format
        return Carbon::parse($birthdate)->format('d.m.Y');
    }

    private function formatGender(string $gender): string
    {
        return ucfirst(strtolower($gender));
    }

    private function generateClientReference(): string
    {
        // Generate a unique client reference (max 40 characters)
        return 'XMLAGENCY_' . time() . '_' . substr(uniqid(), -8);
    }
}
