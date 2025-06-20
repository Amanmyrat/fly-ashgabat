<?php

namespace App\Services\Nemo;

class FlightFilterService
{
    public function getFilterValues(array $flightsData): array
    {
        $minFare = PHP_INT_MAX;
        $maxFare = PHP_INT_MIN;
        $uniqueAirlines = [];
        $uniqueStopCounts = [];

        foreach ($flightsData as $flight) {
            $this->processFlightSegmentsForAirlines($flight, $uniqueAirlines);
            $this->processFlightFaresForMinMax($flight, $minFare, $maxFare);
            $this->processFlightStopCounts($flight, $uniqueStopCounts);
        }
        sort($uniqueStopCounts);

        return [
            'min_price' => $minFare === PHP_INT_MAX ? null : $minFare,
            'max_price' => $maxFare === PHP_INT_MIN ? null : $maxFare,
            'airlines' => $uniqueAirlines,
            'stops' => $uniqueStopCounts,
        ];
    }

    private function processFlightSegmentsForAirlines($flight, &$uniqueAirlines): void
    {
        $segments = $flight->Segments->Segment;
        $segments = is_array($segments) ? $segments : [$segments];

        foreach ($segments as $segment) {
            $airlineCode = $segment->MarkAirline['code'];
            $airlineData = $segment->MarkAirline['airline'];

            if ($airlineData['name'] === null && $airlineData['logo'] === null) {
                continue;
            }

            if (!isset($uniqueAirlines[$airlineCode])) {
                $uniqueAirlines[$airlineCode] = $airlineData;
            }
        }

    }

    private function processFlightFaresForMinMax($flight, &$minFare, &$maxFare): void
    {
        $totalSumAmount = $flight->TotalSum->Amount ?? null;

        if ($totalSumAmount !== null) {
            $minFare = min($minFare, $totalSumAmount);
            $maxFare = max($maxFare, $totalSumAmount);
        }
    }

    private function processFlightStopCounts($flight, &$uniqueStopCounts): void
    {
        if (isset($flight->Outward->StopsCount)) {
            $stopsCount = (int)$flight->Outward->StopsCount;
            if (!in_array($stopsCount, $uniqueStopCounts)) {
                $uniqueStopCounts[] = $stopsCount;
            }
        }

        if (isset($flight->Return->StopsCount)) {
            $stopsCount = (int)$flight->Return->StopsCount;
            if (!in_array($stopsCount, $uniqueStopCounts)) {
                $uniqueStopCounts[] = $stopsCount;
            }
        }
    }

    public function filterFlights(array $flightsData, array $filters): array
    {
        foreach ($flightsData as &$flight) {
            $flight->TotalSum = $this->calculateTotalSum($flight);
        }

        return array_filter($flightsData, function ($flight) use ($filters) {
            return $this->meetsFlightCriteria($flight, $filters);
        });
    }

    public function markCheapestAndFastestFlights(array $flightsData): array
    {
        if (empty($flightsData)) {
            return $flightsData;
        }

        foreach ($flightsData as &$flight) {
            $segments = $flight->Segments->Segment;
            $segments = is_array($segments) ? $segments : [$segments];

            $totalDuration = 0;
            foreach ($segments as $segment) {
                $totalDuration += $segment->FlightTime;
            }

            $totalDuration += $this->calculateStopDurations($flight->Outward ?? null);

            $totalDuration += $this->calculateStopDurations($flight->Return ?? null);

            $flight->TotalDuration = $totalDuration;

            $flight->isCheapest = false;
            $flight->isFastest = false;
        }

        $cheapestFlight = null;
        $lowestPrice = PHP_INT_MAX;

        $fastestFlight = null;
        $shortestDuration = PHP_INT_MAX;

        foreach ($flightsData as $flight) {

            if ($flight->TotalSum->Amount < $lowestPrice) {
                $lowestPrice = $flight->TotalSum->Amount;
                $cheapestFlight = $flight;
            }


            if ($flight->TotalDuration < $shortestDuration) {
                $shortestDuration = $flight->TotalDuration;
                $fastestFlight = $flight;
            }
        }


        if ($cheapestFlight) {
            $cheapestFlight->isCheapest = true;
        }

        if ($fastestFlight) {
            $fastestFlight->isFastest = true;
        }

        return $flightsData;
    }

    private function calculateStopDurations($direction): int
    {
        if (!$direction || !isset($direction->Stops)) {
            return 0;
        }
        $stops = is_array($direction->Stops) ? $direction->Stops : [$direction->Stops];
        $totalStopDuration = 0;

        foreach ($stops as $stop) {
            if (isset($stop['Duration'])) {
                $hours = $stop['Duration']['Hours'] ?? 0;
                $minutes = $stop['Duration']['Minutes'] ?? 0;
                $totalStopDuration += ($hours * 60) + $minutes;
            }
        }

        return $totalStopDuration;
    }

    private function calculateTotalSum($flight): object
    {
        $prices = $flight->PriceInfo->Price;
        $prices = is_array($prices) ? $prices : [$prices];

        $totalAmount = 0;
        $currency = null;

        foreach ($prices as $price) {
            $passengerFare = $price->PassengerFares->PassengerFare;
            $passengerFare = is_array($passengerFare) ? $passengerFare : [$passengerFare];

            foreach ($passengerFare as $fare) {
                $totalAmount += (float) ($fare->TotalFare->Amount * $fare->Quantity);
                $currency = $fare->TotalFare->Currency;
            }
        }

        return (object)[
            'Amount' => $totalAmount,
            'Currency' => $currency
        ];
    }

    private function meetsFlightCriteria($flight, $filters): bool
    {
        return $this->meetsPriceCriteria($flight->TotalSum->Amount, $filters) &&
            $this->meetsBaggageCriteria($flight, $filters) &&
            $this->meetsAirlinesCriteria($flight, $filters['airlines'] ?? null) &&
            $this->meetsStopsCriteria($flight, $filters);
    }

    private function meetsPriceCriteria($totalFare, $filters): bool
    {
        return (!isset($filters['min_price']) || (float)$totalFare >= (float)$filters['min_price']) &&
            (!isset($filters['max_price']) || (float)$totalFare <= (float)$filters['max_price']);
    }

    private function meetsCurrencyCriteria($currency, $filters): bool
    {
        if (!isset($filters['currency'])) {
            return true;
        }

        return $filters['currency'] === $currency;
    }

    private function meetsBaggageCriteria($flight, $filters): bool
    {
        if (!isset($filters['baggage_included'])) {
            return true;
        }

        $hasFreeBaggage = false;
        $prices = $flight->PriceInfo->Price;
        $prices = is_array($prices) ? $prices : [$prices];

        foreach ($prices as $price) {
            $passengerFare = $price->PassengerFares->PassengerFare;
            $passengerFare = is_array($passengerFare) ? $passengerFare : [$passengerFare];

            foreach ($passengerFare as $fare) {
                $tariffs = $fare->Tariffs->Tariff;
                $tariffs = is_array($tariffs) ? $tariffs : [$tariffs];
                foreach ($tariffs as $tariff) {
                    if (isset($tariff->FreeBaggage) && $tariff->FreeBaggage->Value != "0") {
                        $hasFreeBaggage = true;
                        break 2;
                    }
                }
            }
        }

        return $filters['baggage_included'] ? $hasFreeBaggage : true;
    }

    private function meetsAirlinesCriteria($flight, $airlinesFilter): bool
    {
        if ($airlinesFilter === null) {
            return true;
        }

        $segments = $flight->Segments->Segment;
        $segments = is_array($segments) ? $segments : [$segments];
        foreach ($segments as $segment) {
            if (in_array($segment->MarkAirline['code'], $airlinesFilter)) {
                return true;
            }
        }

        return false;
    }

    private function meetsStopsCriteria($flight, $filters): bool
    {
        if (!isset($filters['stops'])) {
            return true;
        }

        $stopsFilter = (int)$filters['stops'];

        $outwardStopsCount = isset($flight->Outward->StopsCount)
            ? (int)$flight->Outward->StopsCount
            : 0;

        $returnStopsCount = isset($flight->Return->StopsCount)
            ? (int)$flight->Return->StopsCount
            : null;

        if ($returnStopsCount === null) {
            return $outwardStopsCount === $stopsFilter;
        }

        if ($stopsFilter === 0) {
            return $outwardStopsCount === 0 && $returnStopsCount === 0;
        } else {
            $maxStops = max($outwardStopsCount, $returnStopsCount);
            return $maxStops === $stopsFilter;
        }
    }
}
