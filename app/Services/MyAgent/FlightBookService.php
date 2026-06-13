<?php

namespace App\Services\MyAgent;

use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Enum\PaymentType;
use App\Models\FlightBooking;
use App\Models\User;
use App\Services\FlightMarkupService;
use App\Services\MyAgent\RequestBuilder\BookRequestBuilder;
use App\Services\MyAgent\Transformers\FlightRecommendationTransformer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FlightBookService
{
    public function __construct(
        protected MyAgentService $myAgentService,
        protected FlightMarkupService $markupService,
        protected FlightRecommendationTransformer $transformer
    ) {
    }

    /**
     * @throws Exception
     */
    public function processBooking(array $validatedData, ?User $user): array
    {
        $requestData = (new BookRequestBuilder($validatedData))->build();

        $response = $this->myAgentService->postForm(
            '/avia/book',
            $requestData,
            (int) config('myagent.book_timeout', 120)
        );

        $book = $response['data']['book'] ?? null;

        if (!is_array($book)) {
            return [
                'success' => false,
                'message' => 'MyAgent booking response did not contain booking data.',
                'data' => $response,
            ];
        }

        $order = $book['order'] ?? [];
        $flight = $book['flight'] ?? [];

        [$amount, $currency] = $this->extractBookingPrice($order);
        $airlineCode = $flight['provider']['supplier']['code']
            ?? $book['tickets'][0]['carrier']['code']
            ?? null;

        [$departureCode, $arrivalCode] = $this->extractRouteCodes($flight);

        $actualPrice = $this->markupService->applyMarkup(
            $amount,
            $currency,
            FlightSupplier::MYAGENT,
            $airlineCode,
            $departureCode,
            $arrivalCode
        );

        if (
            $validatedData['payment_type'] === PaymentType::BALANCE->value
            && (!$user || $user->balance < $actualPrice['Amount'])
        ) {
            return [
                'success' => false,
                'message' => 'Insufficient balance.',
                'balance' => $user?->balance,
                'price' => $actualPrice['Amount'],
            ];
        }

        $journeyData = $this->buildBookingJourneys($flight);

        $bookingData = [
            'user_id' => $user?->id ?? null,
            'booking_reference' => (string) ($order['billing_number'] ?? $order['order_id'] ?? ''),
            'supplier_reference' => (string) ($order['sig'] ?? $order['order_id'] ?? ''),
            'flight_type' => FlightSupplier::MYAGENT,
            'origin' => $journeyData['origin'],
            'destination' => $journeyData['destination'],
            'outward' => $journeyData['outward'],
            'return' => $journeyData['return'],
            'price' => $actualPrice,
            'payment_type' => $validatedData['payment_type'],
            'status' => BookingStatus::PENDING->value,
        ];

        if (empty($bookingData['booking_reference'])) {
            throw new Exception('MyAgent booking reference is missing.');
        }

        $booking = FlightBooking::create($bookingData);

        if (!$booking) {
            throw new Exception('Failed to create booking');
        }

        $booking->contactDetail()->create([
            'email' => $validatedData['contact_details']['email'],
            'phone' => $validatedData['contact_details']['phone'],
            'gender' => null,
            'firstname' => $validatedData['contact_details']['firstname'] ?? null,
            'lastname' => $validatedData['contact_details']['lastname'] ?? null,
            'address' => null,
        ]);

        $travellersData = array_map(function (array $traveller) {
            return [
                'birthdate' => $traveller['birthdate'],
                'passport_number' => $traveller['passport_number'],
                'nationality' => $traveller['nationality'],
                'firstname' => $traveller['firstname'],
                'lastname' => $traveller['lastname'],
                'middlename' => $traveller['middlename'] ?? null,
                'gender' => $traveller['gender'],
                'passport_expiry_date' => $traveller['passport_expiry_date'],
                'passport_country' => $traveller['passport_country'],
            ];
        }, $validatedData['travellers']);

        $booking->travellers()->createMany($travellersData);

        Cache::put(
            'myagent_booking_' . $booking->booking_reference,
            $response,
            now()->addHours(24)
        );

        Log::channel('myagent')->info('MyAgent booking created ' . json_encode([
                'booking_reference' => $booking->booking_reference,
                'supplier_reference' => $booking->supplier_reference,
                'price' => $actualPrice,
                'pid' => $response['pid'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'success' => true,
            'booking' => $booking,
            'raw_meta' => [
                'pid' => $response['pid'] ?? null,
                'execution' => $response['time']['execution'] ?? null,
            ],
        ];
    }

    private function extractBookingPrice(array $order): array
    {
        $prices = $order['price'] ?? [];

        if (isset($prices['USD']['amount'])) {
            return [(float) $prices['USD']['amount'], 'USD'];
        }

        if (isset($prices['RUB']['amount'])) {
            return [(float) $prices['RUB']['amount'], 'RUB'];
        }

        foreach ($prices as $currency => $price) {
            if (isset($price['amount'])) {
                return [(float) $price['amount'], strtoupper((string) $currency)];
            }
        }

        return [0.0, config('myagent.currency', 'USD')];
    }

    private function extractRouteCodes(array $flight): array
    {
        $segments = $flight['segments'] ?? [];

        if (empty($segments)) {
            return [null, null];
        }

        $outwardSegments = array_values(array_filter(
            $segments,
            fn (array $segment) => (int) ($segment['direction'] ?? 0) === 0
        ));

        if (empty($outwardSegments)) {
            $outwardSegments = $segments;
        }

        $first = $outwardSegments[0];
        $last = end($outwardSegments);

        return [
            $first['dep']['airport']['code'] ?? $first['dep']['city']['code'] ?? null,
            $last['arr']['airport']['code'] ?? $last['arr']['city']['code'] ?? null,
        ];
    }

    private function buildBookingJourneys(array $flight): array
    {
        $segments = $flight['segments'] ?? [];

        $outwardSegments = array_values(array_filter(
            $segments,
            fn (array $segment) => (int) ($segment['direction'] ?? 0) === 0
        ));

        $returnSegments = array_values(array_filter(
            $segments,
            fn (array $segment) => (int) ($segment['direction'] ?? 0) === 1
        ));

        if (empty($outwardSegments) && !empty($segments)) {
            $outwardSegments = $segments;
        }

        $firstOutward = $outwardSegments[0] ?? $segments[0] ?? null;
        $lastOutward = !empty($outwardSegments) ? end($outwardSegments) : null;

        return [
            'origin' => $this->airportInfo($firstOutward['dep'] ?? null),
            'destination' => $this->airportInfo($lastOutward['arr'] ?? null),
            'outward' => $this->buildJourneyData($outwardSegments),
            'return' => !empty($returnSegments) ? $this->buildJourneyData($returnSegments) : null,
        ];
    }

    private function buildJourneyData(array $segments): array
    {
        if (empty($segments)) {
            return [];
        }

        $firstSegment = $segments[0];
        $lastSegment = end($segments);

        $departureDateTime = Carbon::createFromFormat('d.m.Y H:i:s', $firstSegment['dep']['datetime']);
        $arrivalDateTime = Carbon::createFromFormat('d.m.Y H:i:s', $lastSegment['arr']['datetime']);

        $durationMinutes = $this->calculateJourneyMinutes($segments);

        return [
            'Duration' => [
                'Hours' => intdiv($durationMinutes, 60),
                'Minutes' => $durationMinutes % 60,
            ],
            'DepartDate' => [
                'Date' => $departureDateTime->format('d/m/Y'),
                'Time' => $departureDateTime->format('H:i'),
            ],
            'ArriveDate' => [
                'Date' => $arrivalDateTime->format('d/m/Y'),
                'Time' => $arrivalDateTime->format('H:i'),
            ],
            'Stops' => $this->calculateStops($segments),
            'StopsCount' => max(count($segments) - 1, 0),
            'Segments' => array_map(fn (array $segment) => $this->buildSegmentData($segment), $segments),
        ];
    }

    private function buildSegmentData(array $segment): array
    {
        $carrier = $segment['carrier']
            ?? $segment['validating_carrier']
            ?? $segment['provider']['supplier']
            ?? [];

        $code = $carrier['code'] ?? null;

        return [
            'FlightNumber' => trim(($code ? $code . '-' : '') . ($segment['flight_number'] ?? '')),
            'Airline' => [
                'Code' => $code,
                'Name' => $carrier['title'] ?? null,
                'Logo' => $code ? 'https://myagent.online/carriers/' . strtoupper($code) . '.png' : null,
            ],
            'Aircraft' => $segment['aircraft_details'] ?? $segment['aircraft'] ?? null,
            'Departure' => array_merge(
                $this->airportInfo($segment['dep'] ?? null),
                ['Date' => $segment['dep']['datetime'] ?? null]
            ),
            'Arrival' => array_merge(
                $this->airportInfo($segment['arr'] ?? null),
                ['Date' => $segment['arr']['datetime'] ?? null]
            ),
            'Duration' => $this->formatDurationFromMinutes(
                (int) ($segment['duration']['flight']['common'] ?? $segment['ticket_duration'] ?? 0)
            ),
            'Class' => $segment['class']['title'] ?? $segment['class']['name'] ?? null,
            'Baggage' => [
                'Checked' => $segment['baggage'] ?? null,
                'Cabin' => $segment['cbaggage'] ?? null,
                'Accessory' => $segment['accessories'] ?? null,
            ],
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

            $arrival = Carbon::createFromFormat('d.m.Y H:i:s', $currentSegment['arr']['datetime']);
            $departure = Carbon::createFromFormat('d.m.Y H:i:s', $nextSegment['dep']['datetime']);

            $minutes = max($arrival->diffInMinutes($departure), 0);

            $stops[] = [
                'Location' => $this->airportInfo($currentSegment['arr'] ?? null),
                'Duration' => [
                    'Hours' => intdiv($minutes, 60),
                    'Minutes' => $minutes % 60,
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

        $firstRouteDuration = $segments[0]['route_duration'] ?? null;

        if (is_numeric($firstRouteDuration) && (int) $firstRouteDuration > 0) {
            return (int) $firstRouteDuration;
        }

        $total = 0;

        foreach ($segments as $segment) {
            $total += (int) ($segment['duration']['flight']['common'] ?? $segment['ticket_duration'] ?? 0);
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

    private function formatDurationFromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}
