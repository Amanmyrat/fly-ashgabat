<?php

namespace App\Services\MyAgent;

class FlightFilterService
{
    public function getFilterValues(array $flights): array
    {
        $airlines = [];
        $stops = [];

        foreach ($flights as $flight) {
            foreach (($flight['_filter']['airlines'] ?? []) as $airlineCode) {
                if (!isset($airlines[$airlineCode])) {
                    $airline = $this->findAirlineByCode($flight, $airlineCode);

                    $airlines[$airlineCode] = [
                        'name' => $airline['Name'] ?? $airline['title'] ?? null,
                        'logo' => $airline['Logo'] ?? $this->airlineLogo($airlineCode),
                    ];
                }
            }

            $outwardStops = $flight['_filter']['stops']['outward'] ?? 0;
            if (!in_array($outwardStops, $stops, true)) {
                $stops[] = $outwardStops;
            }

            $returnStops = $flight['_filter']['stops']['return'] ?? null;
            if ($returnStops !== null && !in_array($returnStops, $stops, true)) {
                $stops[] = $returnStops;
            }
        }

        sort($stops);

        return [
            'airlines' => $airlines,
            'stops' => $stops,
        ];
    }

    public function filterFlights(array $flights, array $filters): array
    {
        return array_values(array_filter($flights, function (array $flight) use ($filters) {
            return $this->meetsAirlinesCriteria($flight, $filters['airlines'] ?? null)
                && $this->meetsStopsCriteria($flight, $filters['stops'] ?? null)
                && $this->meetsBaggageCriteria($flight, $filters['baggage_included'] ?? null);
        }));
    }

    public function removeInternalFields(array $flights): array
    {
        return array_map(function (array $flight) {
            unset($flight['_sort'], $flight['_filter']);
            return $flight;
        }, $flights);
    }

    private function meetsAirlinesCriteria(array $flight, ?array $airlinesFilter): bool
    {
        if (empty($airlinesFilter)) {
            return true;
        }

        $flightAirlines = $flight['_filter']['airlines'] ?? [];

        return !empty(array_intersect($flightAirlines, $airlinesFilter));
    }

    private function meetsStopsCriteria(array $flight, mixed $stopsFilter): bool
    {
        if ($stopsFilter === null || $stopsFilter === '') {
            return true;
        }

        $stopsFilter = (int) $stopsFilter;

        $outwardStops = (int) ($flight['_filter']['stops']['outward'] ?? 0);
        $returnStops = $flight['_filter']['stops']['return'] ?? null;

        if ($returnStops === null) {
            return $outwardStops === $stopsFilter;
        }

        if ($stopsFilter === 0) {
            return $outwardStops === 0 && (int) $returnStops === 0;
        }

        return max($outwardStops, (int) $returnStops) === $stopsFilter;
    }

    private function meetsBaggageCriteria(array $flight, mixed $baggageIncluded): bool
    {
        if ($baggageIncluded === null || $baggageIncluded === '') {
            return true;
        }

        $baggageIncluded = filter_var($baggageIncluded, FILTER_VALIDATE_BOOLEAN);

        if (!$baggageIncluded) {
            return true;
        }

        return (bool) ($flight['_filter']['baggage_included'] ?? false);
    }

    private function findAirlineByCode(array $flight, string $code): array
    {
        foreach (['Outward', 'Return'] as $direction) {
            foreach (($flight[$direction]['Segments'] ?? []) as $segment) {
                if (($segment['Airline']['Code'] ?? null) === $code) {
                    return $segment['Airline'];
                }
            }
        }

        return [];
    }

    private function airlineLogo(string $code): string
    {
        return 'https://myagent.online/carriers/' . strtoupper($code) . '.png';
    }
}
