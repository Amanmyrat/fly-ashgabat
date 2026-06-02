<?php

namespace App\DTO;

final readonly class HotelSearchResultDTO
{
    public function __construct(
        public int $hotelId,
        public ?string $etgId,
        public ?float $latitude,
        public ?float $longitude,
        public string $name,
        public ?int $stars,
        public ?float $priceFrom,
        public string $currency,
        public ?float $score,
        public ?int $reviewsCount,
        public array $images,
        public array $serpFilters,
        public ?FirstRateDTO $firstRate = null,
        public ?string $kind = null,
        public ?string $address = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'hotel_id'      => $this->hotelId,
            'hid'           => $this->hotelId,
            'etg_id'        => $this->etgId,
            'latitude'      => $this->latitude,
            'longitude'     => $this->longitude,
            'name'          => $this->name,
            'stars'         => $this->stars,
            'price_from'    => $this->priceFrom,
            'currency'      => $this->currency,
            'score'         => $this->score,
            'reviews_count' => $this->reviewsCount,
            'images'        => $this->images,
            'serp_filters'  => $this->serpFilters,
            'first_rate'    => $this->firstRate?->toArray(),
            'kind'          => $this->kind,
            'address'       => $this->address,
        ], fn ($v) => $v !== null && $v !== []);
    }
}
