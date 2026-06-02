<?php

namespace App\DTO;

final readonly class HotelPrebookResultDTO
{
    /**
     * @param  array<string, mixed>  $rawOffer
     */
    public function __construct(
        public string  $bookHash,
        public string  $offerId,
        public float   $amount,
        public string  $currency,
        public ?string $freeCancellationBefore,
        public array   $rawOffer,
    ) {}

    /**
     * Build from the ETG /hotel/prebook/ response `data` block.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromEtgResponse(array $data): self
    {
        $offer       = $data['offer'] ?? [];
        $priceDetail = $offer['price_detail'] ?? [];
        $cancelPen   = $offer['cancellation_penalties'] ?? [];

        return new self(
            bookHash:               (string) ($data['book_hash'] ?? ''),
            offerId:                (string) ($offer['offer_id'] ?? ''),
            amount:                 (float)  ($priceDetail['amount'] ?? 0),
            currency:               (string) ($priceDetail['currency_code'] ?? 'USD'),
            freeCancellationBefore: isset($cancelPen['free_cancellation_before'])
                ? (string) $cancelPen['free_cancellation_before']
                : null,
            rawOffer:               $offer,
        );
    }
}
