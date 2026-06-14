<?php

namespace App\Services\MyAgent\RequestBuilder;

use App\Enum\FlightType;
use App\Support\MyAgentLanguage;

class SearchRecommendationsRequestBuilder
{
    public function __construct(protected array $data)
    {
    }

    public function build(): array
    {
        $departureCode = strtoupper($this->data['departure_code']);
        $arrivalCode = strtoupper($this->data['arrival_code']);

        $segments = [
            [
                'from' => $departureCode,
                'to' => $arrivalCode,
                'date' => $this->formatDate($this->data['departure_date']),
            ],
        ];

        if (($this->data['flight_type'] ?? null) === FlightType::ROUND_TRIP->value) {
            $segments[] = [
                'from' => $arrivalCode,
                'to' => $departureCode,
                'date' => $this->formatDate($this->data['arrival_date']),
            ];
        }

        $query = [
            'adt' => (int) ($this->data['adults_count'] ?? 1),
            'chd' => (int) ($this->data['children_count'] ?? 0),
            'inf' => (int) ($this->data['infants_count'] ?? 0),
            'ins' => 0,
            'src' => 0,
            'yth' => 0,
            'class' => $this->mapClass($this->data['class_type'] ?? 'economy'),
            'segments' => $segments,
            'lang' => MyAgentLanguage::resolve(),
        ];

        if (!empty($this->data['count'])) {
            $query['count'] = (int) $this->data['count'];
        }

        if (isset($this->data['is_direct_only'])) {
            $query['is_direct_only'] = (int) filter_var($this->data['is_direct_only'], FILTER_VALIDATE_BOOLEAN);
        }

        return $query;
    }

    private function mapClass(string $classType): string
    {
        return match (strtolower($classType)) {
            'economy' => 'e',
            'business' => 'b',
            'first' => 'f',
            'premium_economy' => 'w',
            default => 'a',
        };
    }

    private function formatDate(string $date): string
    {
        return date('d.m.Y', strtotime($date));
    }
}
