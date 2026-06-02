<?php

namespace App\Services\MyAgent\RequestBuilder;

class FlightDetailsRequestBuilder
{
    public function __construct(protected array $data)
    {
    }

    public function buildFareFamiliesQuery(): array
    {
        return [
            'tid' => $this->data['id'],
            'lang' => config('myagent.lang', 'ru'),
        ];
    }

    public function buildRulesQuery(): array
    {
        return [
            'tid' => $this->data['id'],
            'lang' => config('myagent.lang', 'ru'),
        ];
    }
}
