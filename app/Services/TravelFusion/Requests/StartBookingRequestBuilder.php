<?php

namespace App\Services\TravelFusion\Requests;

class StartBookingRequestBuilder
{
    protected array $data;

    public function __construct(array $validatedData)
    {
        $this->data = $validatedData;
    }

    public function build(): array
    {
        return [
            'StartBooking' => [
                'XmlLoginId' => '', // Placeholder, will be added dynamically
                'LoginId' => '',   // Placeholder, will be added dynamically
                'TFBookingReference' => $this->data['tf_booking_reference'],
                'ExpectedPrice' => $this->data['price'],
//                'FakeBooking' => [
//                    'EnableFakeBooking' => 'true',
//                    'FakeBookingSimulatedDelaySeconds' => '15',
//                    'FakeBookingStatus' => 'Succeeded',
//                    'EnableFakeCardVerification' => 'false',
//                ],
            ],
        ];

    }

}
