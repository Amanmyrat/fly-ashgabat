<?php

namespace App\Services\MyAgent;

use Carbon\Carbon;

class FlightFilterService
{
    public function getFilterValues(array $flights): array
    {
        $airlines = [];
        $departureAirports = [];
        $arrivalAirports = [];
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

            foreach ($this->departureAirportCodes($flight) as $airportCode) {
                if (!isset($departureAirports[$airportCode])) {
                    $departureAirports[$airportCode] = $this->airportDetails($flight['Origin'] ?? [], $airportCode);
                }
            }

            foreach ($this->arrivalAirportCodes($flight) as $airportCode) {
                if (!isset($arrivalAirports[$airportCode])) {
                    $arrivalAirports[$airportCode] = $this->airportDetails($flight['Destination'] ?? [], $airportCode);
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

        ksort($airlines);
        ksort($departureAirports);
        ksort($arrivalAirports);
        sort($stops);

        return [
            'airlines' => $airlines,
            'departure_airports' => $departureAirports,
            'arrival_airports' => $arrivalAirports,
            'stops' => $stops,
            'night_layover' => $this->yesNoFilterOptions(),
            'baggage_recheck' => $this->yesNoFilterOptions(),
        ];
    }

    private function yesNoFilterOptions(): array
    {
        return [
            ['value' => true, 'label' => 'Yes'],
            ['value' => false, 'label' => 'No'],
        ];
    }

    public function filterFlights(array $flights, array $filters): array
    {
        return array_values(array_filter($flights, function (array $flight) use ($filters) {
            return $this->meetsAirlinesCriteria($flight, $filters['airlines'] ?? null)
                && $this->meetsDepartureAirportsCriteria($flight, $filters['departure_airports'] ?? null)
                && $this->meetsArrivalAirportsCriteria($flight, $filters['arrival_airports'] ?? null)
                && $this->meetsStopsCriteria($flight, $filters['stops'] ?? null)
                && $this->meetsBaggageCriteria($flight, $filters['baggage_included'] ?? null)
                && $this->meetsYesNoFilterCriteria($flight, 'night_layover', $filters['night_layover'] ?? null)
                && $this->meetsYesNoFilterCriteria($flight, 'baggage_recheck', $filters['baggage_recheck'] ?? null);
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

    private function meetsDepartureAirportsCriteria(array $flight, ?array $departureAirportsFilter): bool
    {
        if (empty($departureAirportsFilter)) {
            return true;
        }

        $flightDepartureAirports = $this->departureAirportCodes($flight);

        return !empty(array_intersect($flightDepartureAirports, $departureAirportsFilter));
    }

    private function meetsArrivalAirportsCriteria(array $flight, ?array $arrivalAirportsFilter): bool
    {
        if (empty($arrivalAirportsFilter)) {
            return true;
        }

        $flightArrivalAirports = $this->arrivalAirportCodes($flight);

        return !empty(array_intersect($flightArrivalAirports, $arrivalAirportsFilter));
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

    private function meetsYesNoFilterCriteria(array $flight, string $filterKey, mixed $filterValue): bool
    {
        if ($filterValue === null || $filterValue === '') {
            return true;
        }

        $expected = filter_var($filterValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($expected === null) {
            return true;
        }

        $actual = match ($filterKey) {
            'night_layover' => $this->flightHasNightLayover($flight),
            'baggage_recheck' => $this->flightHasBaggageRecheck($flight),
            default => (bool) ($flight['_filter'][$filterKey] ?? false),
        };

        return $actual === $expected;
    }

    private function flightHasNightLayover(array $flight): bool
    {
        if (array_key_exists('night_layover', $flight['_filter'] ?? [])) {
            return (bool) $flight['_filter']['night_layover'];
        }

        foreach (['Outward', 'Return'] as $direction) {
            if ($this->hasNightLayoverInTransformedDirection($flight[$direction]['Segments'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    private function flightHasBaggageRecheck(array $flight): bool
    {
        return (bool) ($flight['_filter']['baggage_recheck'] ?? false);
    }

    private function hasNightLayoverInTransformedDirection(array $segments): bool
    {
        if (count($segments) <= 1) {
            return false;
        }

        for ($i = 0; $i < count($segments) - 1; $i++) {
            $arrival = $this->parseSegmentDateTime($segments[$i]['Arrival']['Date'] ?? null);
            $departure = $this->parseSegmentDateTime($segments[$i + 1]['Departure']['Date'] ?? null);

            if ($arrival && $departure && $this->layoverOverlapsNight($arrival, $departure)) {
                return true;
            }
        }

        return false;
    }

    private function layoverOverlapsNight(Carbon $arrival, Carbon $departure): bool
    {
        if (!$departure->gt($arrival)) {
            return false;
        }

        $day = $arrival->copy()->startOfDay()->subDay();

        while ($day->lte($departure)) {
            $nightStart = $day->copy()->setTime(23, 0, 0);
            $nightEnd = $day->copy()->addDay()->setTime(6, 0, 0);

            if ($arrival->lt($nightEnd) && $nightStart->lt($departure)) {
                return true;
            }

            $day->addDay();
        }

        return false;
    }

    private function parseSegmentDateTime(?string $dateTime): ?Carbon
    {
        if (!$dateTime) {
            return null;
        }

        return \Carbon\Carbon::createFromFormat('d.m.Y H:i:s', $dateTime);
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

    private function airportDetails(array $airport, string $code): array
    {
        return [
            'name' => $airport['Name'] ?? '',
            'city' => $airport['City'] ?? '',
            'code' => $airport['Code'] ?? $code,
        ];
    }

    private function departureAirportCodes(array $flight): array
    {
        $codes = $flight['_filter']['departure_airports'] ?? [];

        if (!empty($codes)) {
            return $codes;
        }

        $code = $flight['Origin']['Code'] ?? null;

        return $code ? [$code] : [];
    }

    private function arrivalAirportCodes(array $flight): array
    {
        $codes = $flight['_filter']['arrival_airports'] ?? [];

        if (!empty($codes)) {
            return $codes;
        }

        $code = $flight['Destination']['Code'] ?? null;

        return $code ? [$code] : [];
    }
}
