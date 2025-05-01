<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlightBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_reference' => $this->booking_reference,
            'supplier_reference' => $this->supplier_reference,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'price' => $this->price,
            'status' => $this->status,
            'payment_type' => $this->payment_type,
        ];
    }
}
