<?php

namespace App\Services\TravelFusion\Requests;

class ListSupplierRoutesRequestBuilder
{
    public function __construct(
        protected string $supplierCode,
        protected bool $oneWayOnlyAirportRoutes = false
    ) {
    }

    public function build(): array
    {
        return [
            'ListSupplierRoutes' => [
                'XmlLoginId' => '', // Will be added by service
                'LoginId' => '',   // Will be added by service
                'Supplier' => $this->supplierCode,
                'OneWayOnlyAirportRoutes' => $this->oneWayOnlyAirportRoutes ? 'true' : 'false'
            ]
        ];
    }
} 