<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisaResource extends JsonResource
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
            'location' => $this->resource->location,
            'description' => $this->resource->description,
            'days' => $this->resource->days,
            'price' => $this->resource->price,
            'necessary_documents' => $this->resource->necessary_documents,
            'main_image' => $this->resource->main_image ? asset('storage/' . $this->resource->main_image) : null,
        ];
    }
}
