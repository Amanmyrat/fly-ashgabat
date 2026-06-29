<?php

namespace App\Services\MyAgent\Transformers;

use App\Enum\FlightSupplier;
use App\Services\FlightMarkupService;
use Carbon\Carbon;

class FlightRecommendationTransformer
{
    public function __construct(
        protected FlightMarkupService $markupService
    ) {
    }

    public function transformMany(array $recommendations, array $searchData = []): array
    {
        return array_values(array_map(
            fn (array $recommendation) => $this->transform($recommendation, $searchData),
            $recommendations
        ));
    }

    public function transform(array $recommendation, array $searchData = []): array
    {
        $segments = $recommendation['segments'] ?? [];

        $outwardSegments = $this->getSegmentsByDirection($recommendation, 0);
        $returnSegments = $this->getSegmentsByDirection($recommendation, 1);

        if (empty($outwardSegments) && !empty($segments)) {
            $outwardSegments = $segments;
        }

        $firstOutward = $outwardSegments[0] ?? $segments[0] ?? null;
        $lastOutward = !empty($outwardSegments) ? end($outwardSegments) : null;

        $validatingAirline = $recommendation['provider']['supplier']['code']
            ?? $recommendation['provider']['code']
            ?? $firstOutward['validating_carrier']['code']
            ?? $firstOutward['carrier']['code']
            ?? null;

        [$amount, $currency] = $this->extractPrice($recommendation);

        $departureCode = $firstOutward['dep']['airport']['code']
            ?? $firstOutward['dep']['city']['code']
            ?? null;

        $arrivalCode = $lastOutward['arr']['airport']['code']
            ?? $lastOutward['arr']['city']['code']
            ?? null;

        $priceWithMarkup = $this->markupService->applyMarkup(
            $amount,
            $currency,
            FlightSupplier::MYAGENT,
            $validatingAirline,
            $departureCode,
            $arrivalCode
        );

        return [
            'id' => $recommendation['id'] ?? null,

            'Origin' => $this->airportInfo($firstOutward['dep'] ?? null),
            'Destination' => $this->airportInfo($lastOutward['arr'] ?? null),

            'TotalSum' => $priceWithMarkup,

            'Outward' => $this->buildJourneyData($outwardSegments),

            'Return' => !empty($returnSegments)
                ? $this->buildJourneyData($returnSegments)
                : null,

            // Extra useful MyAgent fields. Frontend can ignore these.
            'Supplier' => FlightSupplier::MYAGENT->value,
            'Provider' => [
                'Gds' => $recommendation['provider']['gds'] ?? null,
                'Name' => $recommendation['provider']['name'] ?? null,
                'Supplier' => $recommendation['provider']['supplier'] ?? null,
            ],
            'Type' => $recommendation['type'] ?? null,
            'FareFamily' => [
                'Type' => $recommendation['fare_family_type'] ?? null,
                'Name' => $recommendation['fare_family_marketing_name'] ?? null,
                'Flag' => (bool) ($recommendation['fare_family_flag'] ?? false),
            ],
            'Rules' => [
                'Refundable' => (bool) ($recommendation['is_refund'] ?? false),
                'Changeable' => (bool) ($recommendation['is_change'] ?? false),
                'MiniRules' => $recommendation['mini_rules'] ?? null,
            ],

            // Needed for backend sorting/filtering.
            '_sort' => [
                'price' => (float) $priceWithMarkup['Amount'],
                'duration' => (int) ($recommendation['duration'] ?? $this->calculateJourneyMinutes($outwardSegments) + $this->calculateJourneyMinutes($returnSegments)),
                'departure_time' => $this->departureTimestamp($firstOutward),
            ],
            '_filter' => [
                'airlines' => $this->extractAirlineCodes($segments),
                'departure_airports' => array_values(array_filter([$departureCode])),
                'arrival_airports' => array_values(array_filter([$arrivalCode])),
                'stops' => [
                    'outward' => max(count($outwardSegments) - 1, 0),
                    'return' => !empty($returnSegments) ? max(count($returnSegments) - 1, 0) : null,
                ],
                'baggage_included' => (bool) ($recommendation['is_baggage'] ?? false),
                'night_layover' => $this->hasNightLayover($outwardSegments, $returnSegments),
                'baggage_recheck' => $this->hasBaggageRecheck($segments),
            ],
        ];
    }

    private function getSegmentsByDirection(array $recommendation, int $direction): array
    {
        $segments = $recommendation['segments'] ?? [];

        if (empty($segments)) {
            return [];
        }

        $directionIndexes = $recommendation['segments_direction'][$direction] ?? null;

        if (is_array($directionIndexes)) {
            return array_values(array_filter(
                array_map(fn ($index) => $segments[$index] ?? null, $directionIndexes)
            ));
        }

        return array_values(array_filter(
            $segments,
            fn (array $segment) => (int) ($segment['direction'] ?? 0) === $direction
        ));
    }

    private function buildJourneyData(array $segments): array
    {
        if (empty($segments)) {
            return [];
        }

        $firstSegment = $segments[0];
        $lastSegment = end($segments);

        $departure = $this->parseMyAgentDateTime($firstSegment['dep']['datetime'] ?? null);
        $arrival = $this->parseMyAgentDateTime($lastSegment['arr']['datetime'] ?? null);

        $durationMinutes = $this->calculateJourneyMinutes($segments);

        return [
            'Duration' => [
                'Hours' => intdiv($durationMinutes, 60),
                'Minutes' => $durationMinutes % 60,
            ],
            'DepartDate' => [
                'Date' => $departure?->format('d/m/Y'),
                'Time' => $departure?->format('H:i'),
            ],
            'ArriveDate' => [
                'Date' => $arrival?->format('d/m/Y'),
                'Time' => $arrival?->format('H:i'),
            ],
            'Stops' => $this->calculateStops($segments),
            'StopsCount' => max(count($segments) - 1, 0),
            'Segments' => array_values(array_map(
                fn (array $segment) => $this->transformSegment($segment),
                $segments
            )),
        ];
    }

    private function transformSegment(array $segment): array
    {
        $marketingCarrier = $segment['marketing_supplier']
            ?? $segment['carrier']
            ?? $segment['validating_carrier']
            ?? [];

        $flightNumber = trim(($marketingCarrier['code'] ?? '') . '-' . ($segment['flight_number'] ?? ''));

        return [
            'FlightNumber' => $flightNumber,

            'Airline' => [
                'Code' => $marketingCarrier['code'] ?? null,
                'Name' => $marketingCarrier['title'] ?? null,
                'Logo' => $this->airlineLogo($marketingCarrier['code'] ?? null),
            ],

            'Departure' => array_merge(
                $this->airportInfo($segment['dep'] ?? null),
                ['Date' => $segment['dep']['datetime'] ?? null]
            ),

            'Arrival' => array_merge(
                $this->airportInfo($segment['arr'] ?? null),
                ['Date' => $segment['arr']['datetime'] ?? null]
            ),

            'Duration' => $this->formatDurationFromMinutes(
                (int) ($segment['duration']['flight']['common'] ?? 0)
            ),

            'Class' => $this->mapClass($segment['class']['name'] ?? null),

            'Baggage' => [
                'Checked' => $this->transformBaggage(
                    $segment['baggage'] ?? null,
                    $segment['mini_rules']['baggage'] ?? null,
                    $segment['is_baggage'] ?? null
                ),
                'Cabin' => $this->transformBaggage(
                    $segment['cbaggage'] ?? null,
                    $segment['mini_rules']['carry_on_baggage'] ?? null,
                    $segment['mini_rules']['carry_on_baggage']['is_available'] ?? null
                ),
                'Accessory' => $this->transformBaggage(
                    $segment['accessories'] ?? null,
                    $segment['mini_rules']['accessories'] ?? null,
                    $segment['mini_rules']['accessories']['is_available'] ?? null
                ),
            ],

            // Extra MyAgent info.
            'Seats' => $segment['seats'] ?? null,
            'Aircraft' => $segment['aircraft'] ?? null,
            'Refundable' => (bool) ($segment['is_refund'] ?? false),
            'Changeable' => (bool) ($segment['is_change'] ?? false),
            'MiniRules' => $segment['mini_rules'] ?? null,
        ];
    }

    private function calculateStops(array $segments): array
    {
        $stops = [];

        if (count($segments) <= 1) {
            return $stops;
        }

        for ($i = 0; $i < count($segments) - 1; $i++) {
            $currentSegment = $segments[$i];
            $nextSegment = $segments[$i + 1];

            $arrival = $this->parseMyAgentDateTime($currentSegment['arr']['datetime'] ?? null);
            $departure = $this->parseMyAgentDateTime($nextSegment['dep']['datetime'] ?? null);

            $layoverMinutes = 0;

            if ($arrival && $departure) {
                $layoverMinutes = max($arrival->diffInMinutes($departure), 0);
            }

            $stops[] = [
                'Location' => $this->airportInfo($currentSegment['arr'] ?? null),
                'Duration' => [
                    'Hours' => intdiv($layoverMinutes, 60),
                    'Minutes' => $layoverMinutes % 60,
                ],
            ];
        }

        return $stops;
    }

    private function calculateJourneyMinutes(array $segments): int
    {
        if (empty($segments)) {
            return 0;
        }

        $routeDuration = $segments[0]['route_duration'] ?? null;

        if (is_numeric($routeDuration) && (int) $routeDuration > 0) {
            return (int) $routeDuration;
        }

        $total = 0;

        foreach ($segments as $segment) {
            $total += (int) ($segment['duration']['flight']['common'] ?? 0);
            $total += (int) ($segment['duration']['transfer']['common'] ?? 0);
        }

        return $total;
    }

    private function airportInfo(?array $point): array
    {
        if (!$point) {
            return [
                'Code' => null,
                'Name' => '',
                'City' => '',
            ];
        }

        return [
            'Code' => $point['airport']['code'] ?? $point['city']['code'] ?? null,
            'Name' => $point['airport']['title'] ?? '',
            'City' => $point['city']['title'] ?? '',
        ];
    }

    private function extractPrice(array $recommendation): array
    {
        $prices = $recommendation['price'] ?? [];

        $preferredCurrency = strtoupper(config('myagent.currency', 'USD'));

        if (isset($prices[$preferredCurrency]['amount'])) {
            return [
                (float) $prices[$preferredCurrency]['amount'],
                $preferredCurrency,
            ];
        }

        if (isset($prices['USD']['amount'])) {
            return [
                (float) $prices['USD']['amount'],
                'USD',
            ];
        }

        if (isset($prices['RUB']['amount'])) {
            return [
                (float) $prices['RUB']['amount'],
                'RUB',
            ];
        }

        foreach ($prices as $currency => $price) {
            if (isset($price['amount'])) {
                return [
                    (float) $price['amount'],
                    strtoupper((string) $currency),
                ];
            }
        }

        return [0.0, config('myagent.currency', 'USD')];
    }

    private function transformBaggage(?array $baggage, ?array $miniRule, mixed $available): ?array
    {
        if (!$baggage && !$miniRule) {
            return null;
        }

        $piece = $baggage['piece'] ?? $miniRule['piece'] ?? null;
        $weight = $baggage['weight'] ?? $miniRule['weight']['value'] ?? null;
        $unit = $baggage['weight_unit'] ?? $miniRule['weight']['unit'] ?? null;

        $isAvailable = filter_var($available ?? $miniRule['is_available'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$isAvailable && ((int) $piece === 0 || $piece === null) && $weight === null) {
            return [
                'Type' => 'Nil',
                'Count' => 0,
                'Description' => $miniRule['comment'] ?? 'All baggage for a fee',
            ];
        }

        return [
            'Type' => $weight ? 'Kilos' : 'Pieces',
            'Count' => $piece !== null ? (int) $piece : null,
            'Weight' => $weight !== null ? (int) $weight : null,
            'Unit' => $unit,
            'Description' => $miniRule['comment'] ?? $this->buildBaggageDescription($piece, $weight, $unit),
        ];
    }

    private function buildBaggageDescription(mixed $piece, mixed $weight, mixed $unit): string
    {
        if ($weight) {
            return trim($weight . ' ' . strtolower((string) $unit));
        }

        if ($piece) {
            return $piece . ' piece' . ((int) $piece > 1 ? 's' : '');
        }

        return 'Baggage information unknown';
    }

    private function extractAirlineCodes(array $segments): array
    {
        $codes = [];

        foreach ($segments as $segment) {
            $code = $segment['marketing_supplier']['code']
                ?? $segment['carrier']['code']
                ?? $segment['validating_carrier']['code']
                ?? null;

            if ($code) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    private function departureTimestamp(?array $segment): int
    {
        return (int) ($segment['dep']['ts'] ?? 0);
    }

    private function hasNightLayover(array $outwardSegments, array $returnSegments): bool
    {
        return $this->hasNightLayoverInDirection($outwardSegments)
            || $this->hasNightLayoverInDirection($returnSegments);
    }

    private function hasNightLayoverInDirection(array $segments): bool
    {
        if (count($segments) <= 1) {
            return false;
        }

        for ($i = 0; $i < count($segments) - 1; $i++) {
            $arrival = $this->parseMyAgentDateTime($segments[$i]['arr']['datetime'] ?? null);
            $departure = $this->parseMyAgentDateTime($segments[$i + 1]['dep']['datetime'] ?? null);

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

    private function hasBaggageRecheck(array $segments): bool
    {
        foreach ($segments as $segment) {
            if (($segment['baggage_recheck'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    private function parseMyAgentDateTime(?string $dateTime): ?Carbon
    {
        if (!$dateTime) {
            return null;
        }

        return Carbon::createFromFormat('d.m.Y H:i:s', $dateTime);
    }

    private function formatDurationFromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    private function mapClass(?string $class): string
    {
        return match (strtoupper((string) $class)) {
            'B' => 'Business',
            'F' => 'First',
            'W' => 'PremiumEconomy',
            default => 'Econom',
        };
    }

    private function airlineLogo(?string $code): ?string
    {
        if (!$code) {
            return null;
        }

        return 'https://myagent.online/carriers/' . strtoupper($code) . '.png';
    }
}
