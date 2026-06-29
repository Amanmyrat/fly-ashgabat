<?php

namespace App\Services\MyAgent;

class FlightSortService
{
    public function sortFlights(array &$flights, string $sort): void
    {
        if ($sort === 'default') {
            return;
        }

        usort($flights, function (array $a, array $b) use ($sort) {
            $field = ltrim($sort, '-');

            $result = match ($field) {
                'price' => $this->priceValue($a) <=> $this->priceValue($b),
                'duration' => (int) ($a['_sort']['duration'] ?? 0) <=> (int) ($b['_sort']['duration'] ?? 0),
                'departure_time' => (int) ($a['_sort']['departure_time'] ?? 0) <=> (int) ($b['_sort']['departure_time'] ?? 0),
                default => 0,
            };

            return str_starts_with($sort, '-') ? -$result : $result;
        });
    }

    private function priceValue(array $flight): float
    {
        if (isset($flight['_sort']['price'])) {
            return (float) $flight['_sort']['price'];
        }

        return (float) ($flight['TotalSum']['Amount'] ?? 0);
    }
}
