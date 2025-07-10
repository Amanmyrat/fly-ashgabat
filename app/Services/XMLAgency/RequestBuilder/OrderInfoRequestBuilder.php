<?php

namespace App\Services\XMLAgency\RequestBuilder;

use App\Models\FlightBooking;

class OrderInfoRequestBuilder
{
    private FlightBooking $booking;

    public function __construct(FlightBooking $booking)
    {
        $this->booking = $booking;
    }

    public function build(): array
    {
        return [
            'OrderInfo' => [
                'credentials' => $this->buildCredentials(),
                'orderInfoParams' => $this->buildConfirmBookParams(),
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

    private function buildConfirmBookParams(): array
    {
        return [
            'BookGuid' =>  $this->booking->supplier_reference,
            'BookId' =>  $this->booking->booking_reference,
        ];
    }
}
