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
            'id' => $this->resource->id,
            'booking_reference' => $this->resource->booking_reference,
            'service' => $this->resource->service,
            'price' => $this->resource->price,
            'status' => $this->resource->status,
            'expire_at' => $this->resource->expire_at,
            'pay_at' => $this->resource->pay_at,
            'passengers' => PassengerResource::collection($this->resource->passengers),
            'tickets' => TicketResource::collection($this->resource->tickets)
        ];
    }
}
