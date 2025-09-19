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
            'layover_hours' => $this->resource->layover_hours,
            'layover_minutes' => $this->resource->layover_minutes,
            'layover_formatted' => $this->resource->formatted_layover,
            'arrival_weekday' => $this->resource->arrival_weekday,
            'arrival_time' => $this->resource->arrival_time?->format('H:i'),
            'arrival_formatted' => $this->resource->arrival_weekday . ' at ' . $this->resource->arrival_time?->format('H:i'),
            'price' => $this->resource->price,
        ];
    }
} 