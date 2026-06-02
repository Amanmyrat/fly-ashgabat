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
                'price' => ($a['TotalSum']['Amount'] ?? 0) <=> ($b['TotalSum']['Amount'] ?? 0),
                'duration' => ($a['_sort']['duration'] ?? 0) <=> ($b['_sort']['duration'] ?? 0),
                'departure_time' => ($a['_sort']['departure_time'] ?? 0) <=> ($b['_sort']['departure_time'] ?? 0),
                default => 0,
            };

            return str_starts_with($sort, '-') ? -$result : $result;
        });
    }
}
