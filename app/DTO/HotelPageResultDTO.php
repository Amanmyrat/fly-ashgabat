<?php

namespace App\DTO;

final readonly class HotelPageResultDTO
{
    /**
     * @param  array<int, mixed>|null  $descriptionStruct  Raw ETG description_struct blocks
     * @param  array<int, mixed>|null  $amenityGroups  Raw ETG amenity_groups
     * @param  array<int, array{room_name: string, images: array, amenities: array, plans: HotelRoomPlanDTO[]}>  $roomOffers
     */
    public function __construct(
        public int $hotelId,
        public ?string $etgId = null,
        public string $name = '',
        public ?int $stars = null,
        public ?string $address = null,
        public ?array $descriptionStruct = null,
        public ?array $amenityGroups = null,
        public array $images = [],
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?RegionInfoDTO $region = null,
        public ?string $kind = null,
        public ?string $checkInTime = null,
        public ?string $checkOutTime = null,
        public ?array $roomOffers = null,
        public int $reviewsCount = 0,
        public ?float $avgRating = null,
    ) {}

    /**
     * @return array{
     * hotel: array<string, mixed>,
     * rooms: array<int, array<string, mixed>>|null,
     * meta: array{
     * reviews_count: int,
     * avg_rating: float|null
     * }
     * }
     */
    public function toApiResponse(): array
    {
        $rooms = null;

        if (is_array($this->roomOffers)) {
            $rooms = [];

            foreach ($this->roomOffers as $room) {
                $plans = [];

                foreach ($room['plans'] as $plan) {
                    /** @var HotelRoomPlanDTO $plan */
                    $plans[] = $plan->toArray();
                }

                $rooms[] = [
                    'room_name'     => $room['room_name'],
                    'size_m2'       => $room['size_m2'] ?? null,
                    'room_features' => $room['room_features'] ?? [],
                    'images'        => $room['images'],
                    'amenities'     => $room['amenities'],
                    'plans'         => $plans,
                ];
            }
        }
        return [
            'hotel' => $this->buildHotelBlock(),
            'rooms' => $rooms,
            'meta'  => [
                'reviews_count' => $this->reviewsCount,
                'avg_rating'    => $this->avgRating,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHotelBlock(): array
    {
        return [
            'hotel_id'        => $this->hotelId,
            'hid'             => $this->hotelId,
            'etg_id'          => $this->etgId,
            'name'            => $this->name,
            'stars'           => $this->stars,
            'address'         => $this->address,
            'images'          => $this->images,
            'description'     => $this->descriptionStruct ?? [],
            'amenity_groups'  => $this->amenityGroups ?? [],
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'check_in_time'   => $this->checkInTime,
            'check_out_time'  => $this->checkOutTime,
            'kind'            => $this->kind,
            'region'          => $this->region?->toArray(),
        ];
    }

    /**
     * @deprecated Use {@see toApiResponse()}
     * @return array{hotel: array<string, mixed>, rooms: array<int, array<string, mixed>>, meta: array{reviews_count: int, avg_rating: float|null}}
     */
    public function toArray(): array
    {
        return $this->toApiResponse();
    }
}
