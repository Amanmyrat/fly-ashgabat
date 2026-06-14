<?php

namespace App\Services\MyAgent\RequestBuilder;

use App\Support\MyAgentLanguage;

class FlightDetailsRequestBuilder
{
    public function __construct(protected array $data)
    {
    }

    public function buildFareFamiliesQuery(): array
    {
        return [
            'tid' => $this->data['id'],
            'lang' => MyAgentLanguage::resolve(),
        ];
    }

    public function buildRulesQuery(): array
    {
        return [
            'tid' => $this->data['id'],
            'lang' => MyAgentLanguage::resolve(),
        ];
    }
}
