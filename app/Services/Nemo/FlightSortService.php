<?php

namespace App\Services\Nemo;

class FlightSortService
{
    public function sortFlights(&$flightsData, $sort): void
    {
        foreach ($flightsData as $flight) {
            $segments = $flight->Segments->Segment;
            $segments = is_array($segments) ? $segments : [$segments];

            $flight->sortCache = [
                'price' => $flight->TotalSum['Amount'],
                'duration' => $flight->TotalDuration,
                'departure_time' => strtotime($segments[0]->DepDateTime),
            ];
        }

        // Sort using cached values
        usort($flightsData, function($a, $b) use ($sort) {
            $result = match ($sort) {
                'price', '-price' => $a->sortCache['price'] <=> $b->sortCache['price'],
                'duration', '-duration' => $a->sortCache['duration'] <=> $b->sortCache['duration'],
                'departure_time', '-departure_time' => $a->sortCache['departure_time'] <=> $b->sortCache['departure_time'],
                default => 0,
            };

            return $sort[0] === '-' ? -$result : $result;
        });

        foreach ($flightsData as $flight) {
            unset($flight->sortCache);
        }
    }
}
