<?php

namespace App\DTO;

final readonly class HotelRoomPlanDTO
{
    public function __construct(
        public ?float  $priceTotal,
        public ?float  $pricePerNight,
        public string  $currency,
        /** @var array{rooms_left:int|null, level:string}|null */
        public ?array $availability,
        public ?string $mealType,
        public ?bool   $hasBreakfast,
        public ?string $cancellationFreeUntil,
        public ?array  $cancellationPolicy,
        public string  $paymentType,
        public ?array  $taxes,
        public ?string $bookHash,
        public ?string $searchHash,
        public bool    $isCheapest = false,
        /** @var array<string, mixed>|null  ETG `room_data_trans` */
        public ?array $roomData = null,
        /** @var list<string>|null  ETG `serp_filters` */
        public ?array $serpFilters = null,
        /** @var list<string>|null  ETG `amenities_data` */
        public ?array $amenitiesData = null,
        /** @var array<string, mixed>|null  ETG `no_show` */
        public ?array $noShow = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'price_total'             => $this->priceTotal,
            'price_per_night'         => $this->pricePerNight,
            'currency'                => $this->currency,
            'availability'            => $this->availability,
            'meal_type'               => $this->mealType,
            'has_breakfast'           => $this->hasBreakfast ?? false,
            'cancellation_free_until' => $this->cancellationFreeUntil,
            'cancellation_policy'     => $this->cancellationPolicy ?? [],
            'payment_type'            => $this->paymentType,
            'taxes'                   => $this->taxes ?? [],
            'room_data'               => $this->roomData,
            'serp_filters'            => $this->serpFilters ?? [],
            'amenities_data'          => $this->amenitiesData ?? [],
            'no_show'                 => $this->noShow,
            'book_hash'               => $this->bookHash ?? '',
            'search_hash'             => $this->searchHash,
            'is_cheapest'             => $this->isCheapest,
        ];
    }
}
