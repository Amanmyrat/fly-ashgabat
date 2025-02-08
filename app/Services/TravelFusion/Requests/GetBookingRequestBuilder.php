<?php

namespace App\Services\TravelFusion\Requests;

class GetBookingRequestBuilder
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
