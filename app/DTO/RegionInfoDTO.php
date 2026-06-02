<?php

namespace App\DTO;

final readonly class RegionInfoDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $type = null,
        public ?string $countryCode = null,
        public ?string $countryName = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'id'            => $this->id,
            'name'          => $this->name,
            'type'          => $this->type,
            'country_code'  => $this->countryCode,
            'country_name'  => $this->countryName,
            'latitude'      => $this->latitude,
            'longitude'     => $this->longitude,
        ], fn ($v) => $v !== null);
    }
}
