<?php

namespace App\DTO;

final readonly class HotelSearchRequestDTO
{
    public function __construct(
        public int $regionId,
        public string $checkin,
        public string $checkout,
        public string $language,
        public array $guests,
    ) {}

    public function toEtgBody(): array
    {
        return [
            'region_id' => $this->regionId,
            'checkin'   => $this->checkin,
            'checkout'  => $this->checkout,
            'language'  => $this->language,
            'currency' => 'USD',
            'guests'   => array_map(fn (array $g) => [
                'adults'   => $g['adults'],
                'children' => $g['child_ages'] ?? [],
            ], $this->guests),
        ];
    }
}
