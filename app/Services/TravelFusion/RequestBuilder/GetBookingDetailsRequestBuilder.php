<?php

namespace App\Services\TravelFusion\RequestBuilder;

class GetBookingDetailsRequestBuilder
{
    public function __construct(protected string $bookId)
    {
    }

    public function build(): array
    {
        return [
            'GetBookingDetails' => [
                'XmlLoginId' => '', // Placeholder, will be added dynamically
                'LoginId' => '',   // Placeholder, will be added dynamically
                'TFBookingReference' => $this->bookId,
            ],
        ];

    }

}
