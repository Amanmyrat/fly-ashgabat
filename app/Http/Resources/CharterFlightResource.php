<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharterFlightResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'city_from' => new CityResource($this->resource->cityFrom),
            'city_to' => new CityResource($this->resource->cityTo),
            'departure_weekday' => $this->resource->departure_weekday,
            'departure_time' => $this->resource->departure_time?->format('H:i'),
            'departure_formatted' => $this->resource->departure_weekday . ' at ' . $this->resource->departure_time?->format('H:i'),
            'price' => $this->resource->price,
        ];
    }
} 