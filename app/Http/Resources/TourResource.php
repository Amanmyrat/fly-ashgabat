<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourResource extends JsonResource
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
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'location' => $this->resource->location,
            'days' => $this->resource->days,
            'included' => $this->resource->included,
            'not_included' => $this->resource->not_included,
            'main_image' => $this->resource->main_image ? asset('storage/' . $this->resource->main_image) : null,
            'background_image' => $this->resource->background_image ? asset('storage/' . $this->resource->background_image) : null,
        ];
    }
}
