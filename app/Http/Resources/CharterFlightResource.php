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
            'departure_datetime' => $this->resource->departure_datetime,
            'price' => $this->resource->price,
        ];
    }
} 