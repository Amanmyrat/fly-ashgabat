<?php

namespace App\Services\TravelFusion\Requests;

class GetBranchSupplierListRequestBuilder
{
    public function build(): array
    {
        return [
            'GetBranchSupplierList' => [
                'XmlLoginId' => '', // Will be added by service
                'LoginId' => '',   // Will be added by service
            ]
        ];
    }
} 