<?php

namespace App\Services\MyAgent\RequestBuilder;

use Carbon\Carbon;
use InvalidArgumentException;

class BookRequestBuilder
{
    public function __construct(protected array $data)
    {
    }

    public function build(): array
    {
        $contact = $this->data['contact_details'];
        $phone = $this->formatPhone($contact['phone']['code'], $contact['phone']['number']);

        $passengers = array_map(function (array $traveller) use ($contact, $phone) {
            $nationality = strtoupper($traveller['nationality']);

            return [
                'firstname' => $traveller['firstname'],
                'lastname' => $traveller['lastname'],
                'middlename' => $traveller['middlename'] ?? null,
                'age' => $this->calculatePassengerType($traveller['birthdate']),
                'birthdate' => $this->formatDate($traveller['birthdate']),
                'doctype' => $this->mapDocumentType($nationality),
                'docnum' => $traveller['passport_number'],
                'docexp' => $this->formatDate($traveller['passport_expiry_date']),
                'gender' => $this->mapGender($traveller['gender']),
                'citizen' => $nationality,
                'phone' => $phone,
                'email' => $traveller['email'] ?? $contact['email'],
                'send_email' => 0,
            ];
        }, $this->data['travellers']);

        $this->ensureNotChildOnly($passengers);

        return [
            'tid' => $this->data['selected_tariff'] ?? $this->data['flight_id'],
            'client_email' => $contact['email'],
            'client_phone' => $phone,
            'payer_name' => $contact['firstname'] . ' ' . $contact['lastname'],
            'passengers' => $passengers,
            'lang' => strtoupper(config('myagent.lang', 'ru')),
        ];
    }

    private function formatPhone(string $code, string $number): string
    {
        $code = trim($code);
        $number = trim($number);

        $code = preg_replace('/[^\d+]/', '', $code);
        $number = preg_replace('/\D/', '', $number);

        if (!str_starts_with($code, '+')) {
            $code = '+' . ltrim($code, '+');
        }

        return $code . $number;
    }

    private function calculatePassengerType(string $birthdate): string
    {
        $birth = Carbon::parse($birthdate)->startOfDay();
        $age = $birth->age;

        if ($age < 2) {
            return 'inf';
        }

        if ($age < 12) {
            return 'chd';
        }

        return 'adt';
    }

    private function ensureNotChildOnly(array $passengers): void
    {
        $hasAdult = collect($passengers)->contains(
            fn (array $passenger) => ($passenger['age'] ?? null) === 'adt'
        );

        if (!$hasAdult) {
            throw new InvalidArgumentException('Child-only bookings are not supported.');
        }
    }

    private function mapDocumentType(string $nationality): string
    {
        return $nationality === 'RU' ? 'P' : 'A';
    }

    private function mapGender(string $gender): string
    {
        return match (strtolower($gender)) {
            'male' => 'M',
            'female' => 'F',
            default => throw new InvalidArgumentException('Invalid gender value.'),
        };
    }

    private function formatDate(string $date): string
    {
        return Carbon::parse($date)->format('d.m.Y');
    }
}
