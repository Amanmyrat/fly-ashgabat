<?php

namespace App\Services\ETG;

use App\DTO\HotelBookingFormResultDTO;
use App\DTO\HotelBookRequestDTO;
use App\DTO\HotelOrderStatusDTO;
use App\Exceptions\ETG\BookingFailedException;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use RuntimeException;

http://petek.com.tm/hotel-info?hotel_id=8473727&etg_id=test_hotel_do_not_book&distance_from_center_km=3.2&checkin=2026-05-13&checkout=2026-05-16&language=en&guests=[{%22adults%22:2,%22child_ages%22:[]},{%22adults%22:3,%22child_ages%22:[]},{%22adults%22:2,%22child_ages%22:[]}]&hotel={%22hotel_id%22:8473727,%22hid%22:8473727,%22etg_id%22:%22test_hotel_do_not_book%22,%22latitude%22:14.079872,%22longitude%22:-87.21677,%22name%22:%22Test+Hotel+(Do+Not+Book)+test%22,%22stars%22:2,%22price_from%22:3280,%22currency%22:%22USD%22,%22score%22:4.6,%22reviews_count%22:1,%22images%22:[%22https://cdn.worldota.net/t/{size}/extranet/b5/d3/b5d3d33394494c68321246882c1bd93a6832dcd5.jpeg%22,%22https://cdn.worldota.net/t/{size}/extranet/15/44/15440558c952ec4c3488730d1563dc9e8d25832c.JPEG%22,%22https://cdn.worldota.net/t/{size}/extranet/cf/cd/cfcd60bc8fc0cab8fa26c3c63617d541cf1175ee.jpeg%22,%22https://cdn.worldota.net/t/{size}/extranet/6e/5b/6e5b761eaa9e07abfd997ec02d84fa7cf2229fa7.jpeg%22,%22https://cdn.worldota.net/t/{size}/extranet/13/11/13115f9ec6d13ae5a3e9b5afdb693e7f22cfcfe0.jpeg%22,%22https://cdn.worldota.net/t/{size}/extranet/11/80/118098dc06238834a9becb33546314a61fc50ff7.jpeg%22,%22https://cdn.worldota.net/t/{size}/extranet/55/0f/550f03096f8bf9f458bc8662f2dc4d870ba8f853.jpeg%22],%22serp_filters%22:[%22has_internet%22,%22has_parking%22,%22has_spa%22,%22has_pets%22,%22has_jacuzzi%22,%22kitchen%22],%22first_rate%22:{%22amount%22:3280,%22currency%22:%22USD%22,%22room_name%22:%22Suite%22,%22allotment%22:45,%22has_breakfast%22:true,%22free_cancellation_before%22:%222026-05-13T20:00:00%22,%22payment_type%22:%22pay_deposit%22,%22match_hash%22:%22m-1847bb2d-5fe7-503f-be9c-c708917ace03%22},%22kind%22:%22Apartment%22,%22address%22:%22Francisco+Moraz%C3%A1n,+Tegucigalpa%22,%22avg_rating%22:4.6,%22score_qualitative%22:%22Below+average%22,%22distance_from_center_km%22:3.2}
class HotelBookingService
{
    private const PREBOOK_ENDPOINT        = 'api/b2b/v3/hotel/prebook/';
    private const BOOKING_FORM_ENDPOINT   = 'api/b2b/v3/hotel/order/booking/form/';
    private const BOOKING_FINISH_ENDPOINT = 'api/b2b/v3/hotel/order/booking/finish/';
    private const BOOKING_STATUS_ENDPOINT = 'api/b2b/v3/hotel/order/booking/finish/status/';

    /** Seconds between status poll attempts. ETG recommends once per 5s. */
    private const STATUS_RETRY_SLEEP_SECONDS = 5;

    /** Maximum poll attempts before giving up (10 × 5s = 50s max wait). */
    private const MAX_STATUS_RETRIES = 10;

    // ETG Payment types
    private const ETG_DEPOSIT_TYPE = 'deposit';

    public function __construct(private readonly EtgClient $client) {}

    /**
     * Process booking details (Step 1 of 2-step flow).
     * Executes steps 0-1 of ETG flow (prebook + booking form).
     * Returns data needed for payment decision (stripe checkout URL, etc).
     *
     * @return array{partner_order_id: string, order_id: int, book_hash: string, amount: float, currency: string, payment_type: string, checkout_url?: string, prebook?: array<string, mixed>}
     *
     * @throws BookingFailedException  If prebook, order, or payment validation fails.
     */
    public function processDetails(HotelBookRequestDTO $dto, string $userIp, string $userAgent, ?User $user = null): array
    {
        $partnerOrderId = (string) Str::uuid();

        $prebookSummary = null;
        $dtoForBooking  = $dto;

        if (str_starts_with($dto->bookHash, 'h-')) {
            $prebookResult  = $this->prebook($dto->bookHash);
            $dtoForBooking = $dto->withBookHash($prebookResult['book_hash']);
            $prebookSummary = [
                'book_hash'                => $prebookResult['book_hash'],
                'amount'                   => $prebookResult['amount'],
                'currency'                 => $prebookResult['currency'],
                'free_cancellation_before' => $prebookResult['free_cancellation_before'],
            ];
            if (!empty($prebookResult['match_hash'])) {
                $prebookSummary['match_hash'] = $prebookResult['match_hash'];
            }
            // Include hotel and room info for booking
            if (!empty($prebookResult['hotel_id'])) {
                $prebookSummary['hotel_id'] = $prebookResult['hotel_id'];
            }
            if (!empty($prebookResult['room_name'])) {
                $prebookSummary['room_name'] = $prebookResult['room_name'];
            }
        }

        // Step 1 — Create booking form
        $bookingForm = $this->createBookingForm($dtoForBooking, $partnerOrderId, $userIp);

        // Step 2 — Resolve which ETG payment type matches what the user chose
        $etgPaymentType = $this->resolveEtgPaymentType($dtoForBooking->paymentType, $bookingForm->paymentTypes);

        // Check balance availability if paying with balance
        if ($dtoForBooking->paymentType === 'balance') {
            $this->assertBalanceCovers($user, $etgPaymentType);
        }

        $result = [
            'partner_order_id' => $partnerOrderId,
            'order_id'         => $bookingForm->orderId,
            'book_hash'        => $dtoForBooking->bookHash,
            'amount'           => (float) $etgPaymentType['amount'],
            'currency'         => $etgPaymentType['currency_code'],
            'payment_type'     => $dtoForBooking->paymentType,
        ];

        if ($prebookSummary !== null) {
            $result['prebook'] = $prebookSummary;
        }

        return $result;
    }

    /**
     * Execute the ETG booking flow (Step 2 of 2-step flow).
     * Executes steps 2-4 of ETG flow (booking finish + status polling).
     *
     * @return array{partner_order_id: string, status: string}
     *
     */
    public function startBooking(
        HotelBookRequestDTO $dto,
        string $partnerOrderId,
        float $amount,
        string $currency,
    ): array {
        $etgPaymentType = [
            'type'          => 'deposit',
            'amount'        => (string) $amount,
            'currency_code' => strtoupper($currency),
        ];

        $this->finishBooking($dto, $partnerOrderId, $etgPaymentType);

        $status = $this->pollBookingStatus($partnerOrderId);

        if ($status->isFailed()) {
            throw new BookingFailedException(
                "ETG booking failed. partner_order_id: {$partnerOrderId}, status: {$status->status}."
            );
        }

        return [
            'partner_order_id' => $partnerOrderId,
            'status' => $status->status,
        ];
    }


    public function prebook(string $hBookHash): array
    {
        $body = [
            'hash' => $hBookHash,
        ];

        $this->log()->info('ETG hotel/prebook: request.', ['hash' => $hBookHash]);
        $this->log()->debug('ETG hotel/prebook: body.', ['body' => $body]);

        try {
            $response = $this->client->post(self::PREBOOK_ENDPOINT, $body);
        } catch (RequestException $e) {
            $this->convertRequestException($e, 'prebook');
        }

        $data = $response['data'] ?? $response;

        $this->log()->debug('ETG hotel/prebook: response.', ['data' => $data]);

        if (!empty($response['error'])) {
            $error = $response['error'];
            throw new BookingFailedException(
                'ETG prebook error: ' . (is_string($error) ? $error : json_encode($error)),
                'PREBOOK_FAILED',
            );
        }

        $extracted = $this->extractPrebookBookHashAndPricing($data);
        if ($extracted === null) {
            throw new BookingFailedException(
                'ETG prebook did not return a p-... book_hash. Response: ' . json_encode($response),
                'PREBOOK_FAILED',
            );
        }

        return $extracted;
    }

    private function extractPrebookBookHashAndPricing(array $data): ?array
    {
        // Nested hotelpage-style response (typical for /hotel/prebook/)
        foreach ($data['hotels'] ?? [] as $hotel) {
            foreach ($hotel['rates'] ?? [] as $rate) {
                if (empty($rate['book_hash'])) {
                    continue;
                }
                $paymentType = $rate['payment_options']['payment_types'][0] ?? [];
                $cancelPen   = $paymentType['cancellation_penalties'] ?? [];

                $out = [
                    'book_hash'                => (string) $rate['book_hash'],
                    'amount'                   => (float) ($paymentType['amount'] ?? 0),
                    'currency'                 => (string) ($paymentType['currency_code'] ?? 'USD'),
                    'free_cancellation_before' => isset($cancelPen['free_cancellation_before'])
                        ? (string) $cancelPen['free_cancellation_before']
                        : null,
                ];
                if (!empty($rate['match_hash']) && is_string($rate['match_hash'])) {
                    $out['match_hash'] = $rate['match_hash'];
                }
                if (!empty($rate['room_name'])) {
                    $out['room_name'] = $rate['room_name'];
                }
                // Extract hotel ID (hid) from the hotel object
                if (!empty($hotel['hid'])) {
                    $out['hotel_id'] = (int) $hotel['hid'];
                }

                return $out;
            }
        }

        // Flat/alternate shape: book_hash on data root + offer block
        if (!empty($data['book_hash'])) {
            $offer       = $data['offer'] ?? [];
            $priceDetail = $offer['price_detail'] ?? [];
            $cancelPen   = $offer['cancellation_penalties'] ?? [];

            $out = [
                'book_hash'                => (string) $data['book_hash'],
                'amount'                   => (float) ($priceDetail['amount'] ?? 0),
                'currency'                 => (string) ($priceDetail['currency_code'] ?? 'USD'),
                'free_cancellation_before' => isset($cancelPen['free_cancellation_before'])
                    ? (string) $cancelPen['free_cancellation_before']
                    : null,
            ];
            if (!empty($data['match_hash']) && is_string($data['match_hash'])) {
                $out['match_hash'] = $data['match_hash'];
            }

            return $out;
        }

        return null;
    }

    public function getOrderStatus(string $partnerOrderId): array
    {
        $status = $this->fetchBookingStatus($partnerOrderId);

        return [
            'partner_order_id' => $status->partnerOrderId,
            'status'           => $status->status,
            'needs_3ds'        => $status->needs3ds(),
            'data_3ds'         => $status->data3ds,
        ];
    }

    private function createBookingForm(
        HotelBookRequestDTO $dto,
        string              $partnerOrderId,
        string              $userIp,
    ): HotelBookingFormResultDTO {
        $body = $dto->toBookingFormBody($partnerOrderId, $userIp);

        $this->log()->info('ETG booking-form: request.', [
            'partner_order_id' => $partnerOrderId,
            'book_hash'        => $dto->bookHash,
        ]);
        $this->log()->debug('ETG booking-form: body.', ['body' => $body]);

        try {
            $response = $this->client->post(self::BOOKING_FORM_ENDPOINT, $body);
        } catch (RequestException $e) {
            $this->convertRequestException($e, 'booking-form');
        }

        $data = $response['data'] ?? $response;

        $this->log()->debug('ETG booking-form: response.', ['data' => $data]);

        if (!empty($response['error'])) {
            $error = $response['error'];
            throw new BookingFailedException(
                'ETG booking-form error: ' . (is_string($error) ? $error : json_encode($error))
            );
        }

        if (empty($data['order_id'])) {
            throw new RuntimeException(
                'ETG booking-form did not return order_id. Response: ' . json_encode($response)
            );
        }

        return HotelBookingFormResultDTO::fromEtgResponse($data);
    }


    private function assertBalanceCovers(?User $user, array $etgPaymentType): void
    {
        if ($user === null) {
            throw new BookingFailedException(
                'You must be logged in to pay with balance.',
                'BALANCE_AUTH_REQUIRED',
            );
        }

        $user->refresh();

        $required = (float) $etgPaymentType['amount'];
        $currency = $etgPaymentType['currency_code'];
        $available = (float) $user->balance;

        if ($available < $required) {
            throw new BookingFailedException(
                sprintf(
                    'Insufficient balance. Required: %.2f %s, available: %.2f.',
                    $required,
                    $currency,
                    $available,
                ),
                'INSUFFICIENT_BALANCE',
            );
        }
    }

    private function resolveEtgPaymentType(string $ourPaymentType, array $availableTypes): array
    {
        // All payment types map to 'deposit' for ETG
        $etgType = self::ETG_DEPOSIT_TYPE;

        foreach ($availableTypes as $pt) {
            if (($pt['type'] ?? '') === $etgType) {
                return [
                    'type'          => $etgType,
                    'amount'        => (string) $pt['amount'],
                    'currency_code' => (string) $pt['currency_code'],
                ];
            }
        }

        $available = implode(', ', array_column($availableTypes, 'type'));
        throw new BookingFailedException(
            "Payment type 'deposit' is not available for this rate. Available ETG types: [{$available}]."
        );
    }

    private function finishBooking(
        HotelBookRequestDTO $dto,
        string              $partnerOrderId,
        array               $etgPaymentType,
    ): void {
        $body = $dto->toBookingFinishBody($partnerOrderId, $etgPaymentType);

        $this->log()->info('ETG booking-finish: request.', [
            'partner_order_id' => $partnerOrderId,
            'payment_type'     => $etgPaymentType['type'],
            'amount'           => $etgPaymentType['amount'],
            'currency'         => $etgPaymentType['currency_code'],
        ]);
        $this->log()->debug('ETG booking-finish: body.', ['body' => $body]);

        try {
            $response = $this->client->post(self::BOOKING_FINISH_ENDPOINT, $body);
        } catch (RequestException|ConnectionException $e) {
            $statusCode = $e instanceof RequestException ? ($e->response?->status() ?? 0) : 0;

            if ($statusCode >= 400 && $statusCode < 500) {
                // Definitive client error — stop the flow.
                $this->convertRequestException($e, 'booking-finish');
            }

            // 5xx / network: per ETG docs always proceed to poll.
            $this->log()->warning('ETG booking-finish: transient error — will still poll status.', [
                'partner_order_id' => $partnerOrderId,
                'http_status'      => $statusCode,
                'message'          => $e->getMessage(),
            ]);

            return;
        }

        $this->log()->debug('ETG booking-finish: response.', ['response' => $response]);

        if (!empty($response['error'])) {
            $error = $response['error'];

            // Per ETG docs: timeout / unknown are transient — still poll.
            if (in_array($error, ['timeout', 'unknown'], true)) {
                $this->log()->warning('ETG booking-finish: retryable error — will poll status.', [
                    'partner_order_id' => $partnerOrderId,
                    'error'            => $error,
                ]);

                return;
            }

            throw new BookingFailedException(
                'ETG booking-finish error: ' . (is_string($error) ? $error : json_encode($error))
            );
        }
    }

    private function pollBookingStatus(string $partnerOrderId): HotelOrderStatusDTO
    {
        $status = null;

        for ($attempt = 1; $attempt <= self::MAX_STATUS_RETRIES; $attempt++) {
            sleep(self::STATUS_RETRY_SLEEP_SECONDS);

            $this->log()->info('ETG booking-status: polling.', [
                'partner_order_id' => $partnerOrderId,
                'attempt'          => $attempt,
                'max'              => self::MAX_STATUS_RETRIES,
            ]);

            $status = $this->fetchBookingStatus($partnerOrderId);

            $this->log()->info('ETG booking-status: response.', [
                'partner_order_id' => $partnerOrderId,
                'status'           => $status->status,
                'attempt'          => $attempt,
            ]);

            if (!$status->isPending()) {
                return $status;
            }
        }

        // All retries exhausted — return last known status (still processing).
        // The frontend should use the status endpoint to continue polling independently.
        return $status ?? HotelOrderStatusDTO::fromEtgResponse([
            'status' => 'error',
            'data'   => ['partner_order_id' => $partnerOrderId],
        ]);
    }

    /**
     * Single call to /hotel/order/booking/finish/status/.
     */
    private function fetchBookingStatus(string $partnerOrderId): HotelOrderStatusDTO
    {
        $response = $this->client->post(
            self::BOOKING_STATUS_ENDPOINT,
            ['partner_order_id' => $partnerOrderId],
        );

        return HotelOrderStatusDTO::fromEtgResponse($response);
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    private function convertRequestException(RequestException|ConnectionException $e, string $step): never
    {
        $statusCode = $e instanceof RequestException ? ($e->response?->status() ?? 0) : 0;
        $body       = $e instanceof RequestException ? ($e->response?->json() ?? []) : [];
        $rawBody    = $e instanceof RequestException ? ($e->response?->body() ?? $e->getMessage()) : $e->getMessage();

        $this->log()->error("ETG {$step}: HTTP {$statusCode} error.", [
            'status' => $statusCode,
            'body'   => $rawBody,
        ]);

        if ($statusCode >= 400 && $statusCode < 500) {
            $error   = $body['error'] ?? $body['message'] ?? $rawBody;
            $message = is_array($error)
                ? ($error['message'] ?? json_encode($error))
                : (string) $error;

            $errorCode = $step === 'prebook' ? 'PREBOOK_FAILED' : 'BOOKING_FAILED';

            throw new BookingFailedException("ETG {$step} failed [{$statusCode}]: {$message}", $errorCode);
        }

        throw $e;
    }

    private function log(): LoggerInterface
    {
        return Log::channel('etg');
    }
}
