<?php

namespace App\DTO;

use App\Support\EtgLanguage;
use Carbon\Carbon;

final readonly class HotelBookRequestDTO
{
    /**
     * @param  array<int, array{
     *     guests: array<int, array{
     *         first_name?: string|null,
     *         last_name?: string|null,
     *     }>
     * }>  $rooms
     * @param  array{email: string, phone: string}  $contact
     */
    public function __construct(
        public string $bookHash,
        public string $paymentType,
        public string $language,
        public array $rooms,
        public array $contact,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            bookHash:    $data['book_hash'],
            paymentType: $data['payment_type'],
            language:    EtgLanguage::resolve(),
            rooms:       $data['rooms'],
            contact:     $data['contact'],
        );
    }

    /**
     * After ETG prebook, the p-... hash must be used for booking/form and echoed in the API response.
     */
    public function withBookHash(string $bookHash): self
    {
        return new self(
            bookHash: $bookHash,
            paymentType: $this->paymentType,
            language: $this->language,
            rooms: $this->rooms,
            contact: $this->contact,
        );
    }

    /**
     * Step 1 — POST /hotel/order/booking/form/
     * Use the p-... book_hash from ETG prebook (when the client sent an h-... hash, prebook runs server-side first).
     *
     * @return array{partner_order_id: string, book_hash: string, language: string, user_ip: string}
     */
    public function toBookingFormBody(string $partnerOrderId, string $userIp): array
    {
        return [
            'partner_order_id' => $partnerOrderId,
            'book_hash'        => $this->bookHash,
            'language'         => $this->language,
            'user_ip'          => $userIp,
        ];
    }

    /**
     * Step 2 — POST /hotel/order/booking/finish/
     * Submits guest data and starts the async booking.
     *
     * @param  array{type: string, amount: string, currency_code: string}  $etgPaymentType
     * @return array<string, mixed>
     */
    public function toBookingFinishBody(string $partnerOrderId, array $etgPaymentType): array
    {
        $body = [
            'language'     => $this->language,
            'partner'      => [
                'partner_order_id' => $partnerOrderId,
            ],
            'user'         => [
                'email' => $this->contact['email'],
                'phone' => $this->contact['phone'],
            ],
            'rooms'        => array_map(
                fn (array $room) => [
                    'guests' => array_map(
                        fn (array $g) => $this->mapGuest($g),
                        $room['guests'],
                    ),
                ],
                $this->rooms,
            ),
            'payment_type' => $etgPaymentType,
        ];

        if (!empty($this->contact['email'])) {
            $body['supplier_data'] = [
                'first_name_original' => 'Guest',
                'last_name_original' => 'Booking',
                'email' => $this->contact['email'],
                'phone' => $this->contact['phone'],
            ];
        }

        return $body;
    }

    /**
     * @param  array{
     *     first_name?: string|null,
     *     last_name?: string|null,
     * }  $g
     * @return array<string, mixed>
     */
    private function mapGuest(array $g): array
    {
        $guest = [];

        $firstName = trim((string) ($g['first_name'] ?? ''));
        $lastName = trim((string) ($g['last_name'] ?? ''));

        if ($firstName !== '') {
            $guest['first_name'] = $firstName;
        }

        if ($lastName !== '') {
            $guest['last_name'] = $lastName;
        }

        return $guest;
    }
}
