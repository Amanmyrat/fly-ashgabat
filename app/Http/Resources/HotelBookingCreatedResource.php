<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Minimal hotel book response (aligned with flight booking: id, refs, price, documents). */
class HotelBookingCreatedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'partner_order_id' => $this->partner_order_id,
            'order_id'         => $this->etg_order_id,
            'status'           => $this->status,
            'amount'           => (float) $this->amount,
            'currency'         => $this->currency,
            'payment_type'     => $this->payment_type,
            'documents'        => $this->confirmation_pdf_url
                ? [['name' => 'hotel-confirmation.pdf', 'url' => $this->confirmation_pdf_url]]
                : [],
        ];
    }
}
