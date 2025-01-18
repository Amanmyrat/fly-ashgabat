<?php

namespace App\Services\TravelFusion\Requests;

class CheckRoutingRequestBuilder
{
    protected string $routingId;

    public function __construct(string $routingId)
    {
        $this->routingId = $routingId;
    }

    public function build(): array
    {
        return [
            'CheckRouting' => [
                'XmlLoginId' => '', // Placeholder, will be added dynamically
                'LoginId' => '',   // Placeholder, will be added dynamically
                'RoutingId' => $this->routingId,
            ],
        ];
    }
}
