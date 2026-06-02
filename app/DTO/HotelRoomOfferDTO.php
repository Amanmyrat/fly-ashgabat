<?php

namespace App\DTO;

/**
 * One bookable room offer: merged HP rate + hotel/info room group metadata.
 *
 * Use {@see self::toApiArray()} for the public API shape (full fields, no dropped keys).
 */
final readonly class HotelRoomOfferDTO
{
    /**
     * @param  array<int, string>|null  $images
     * @param  array<int, mixed>|null  $amenities  Raw from ETG room group
     * @param  array<int, array{from: ?string, to: ?string, amount: float}>|null  $cancellationPolicyRules
     * @param  array<int, mixed>|null  $taxesRaw  Raw taxes/tax_data from rate
     */
    public function __construct(
        public string $name,
        public ?string $mainRoomType = null,
        public ?array $images = null,
        public ?array $amenities = null,
        public ?float $priceTotal = null,
        public ?float $pricePerNight = null,
        public string $currency = 'USD',
        public ?string $mealType = null,
        public ?bool $hasBreakfast = null,
        public ?string $cancellationFreeUntil = null,
        public ?array $cancellationPolicyRules = null,
        public ?array $taxesRaw = null,
        public ?string $bookHash = null,
        public ?string $searchHash = null,
    ) {}

    /**
     * Full room object for JSON API — all keys present; use null where data is absent.
     *
     * @return array<string, mixed>
     */
    public function toApiArray(bool $isCheapest = false): array
    {
        return [
            'name'                       => $this->name,
            'main_room_type'             => $this->mainRoomType ?? '',
            'images'                     => $this->normalizeImageStrings(),
            'amenities'                  => $this->normalizeAmenityStrings(),
            'price_total'                => $this->priceTotal,
            'price_per_night'            => $this->pricePerNight,
            'currency'                   => $this->currency,
            'meal_type'                  => $this->mealType ?? '',
            'has_breakfast'              => $this->hasBreakfast ?? false,
            'cancellation_free_until'    => $this->cancellationFreeUntil,
            'cancellation_policy'        => $this->cancellationPolicyRules ?? [],
            'taxes'                      => $this->normalizeTaxes(),
            'book_hash'                  => $this->bookHash ?? '',
            'search_hash'                => $this->searchHash,
            'is_cheapest'                => $isCheapest,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeImageStrings(): array
    {
        if ($this->images === null) {
            return [];
        }
        $out = [];
        foreach ($this->images as $img) {
            if (is_string($img) && $img !== '') {
                $out[] = $img;
            } elseif (is_array($img) && isset($img['url'])) {
                $out[] = (string) $img['url'];
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeAmenityStrings(): array
    {
        if ($this->amenities === null) {
            return [];
        }
        $out = [];
        foreach ($this->amenities as $a) {
            if (is_string($a) && $a !== '') {
                $out[] = $a;
            } elseif (is_array($a)) {
                $label = $a['name'] ?? $a['title'] ?? $a['text'] ?? null;
                if ($label !== null && $label !== '') {
                    $out[] = (string) $label;
                }
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{name: string, amount: float, currency: string}>
     */
    private function normalizeTaxes(): array
    {
        if ($this->taxesRaw === null || $this->taxesRaw === []) {
            return [];
        }
        $out = [];
        foreach ($this->taxesRaw as $t) {
            if (!is_array($t)) {
                continue;
            }
            $name = (string) ($t['name'] ?? $t['title'] ?? $t['tax_name'] ?? '');
            $out[] = [
                'name'     => $name,
                'amount'   => (float) ($t['amount'] ?? $t['value'] ?? 0),
                'currency' => (string) ($t['currency'] ?? $t['currency_code'] ?? $this->currency),
            ];
        }

        return $out;
    }
}
