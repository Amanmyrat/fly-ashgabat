<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBooking extends Model
{
    protected $fillable = [
        'user_id',
        'partner_order_id',
        'stripe_session_id',
        'etg_order_id',
        'status',
        'payment_type',
        'book_hash',
        'amount',
        'currency',
        'hotel_id',
        'room_type',
        'rooms_count',
        'adults_count',
        'children_count',
        'contact_email',
        'contact_phone',
        'guests',
        'api_response',
        'confirmation_pdf_url',
    ];

    protected function casts(): array
    {
        return [
            'etg_order_id' => 'integer',
            'amount'       => 'decimal:2',
            'guests'       => 'array',
            'api_response' => 'array',
            'hotel_id'     => 'integer',
            'rooms_count'  => 'integer',
            'adults_count' => 'integer',
            'children_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class, 'hotel_id', 'hid');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'type'             => 'hotel',
            'id'               => $this->id,
            'partner_order_id' => $this->partner_order_id,
            'etg_order_id'     => $this->etg_order_id,
            'status'           => $this->status,
            'payment_type'     => $this->payment_type,
            'amount'           => (float) $this->amount,
            'currency'         => $this->currency,
            'contact_email'    => $this->contact_email,
            'confirmation_pdf_url' => $this->confirmation_pdf_url,
            'documents'        => $this->confirmation_pdf_url
                ? [['name' => 'hotel-confirmation.pdf', 'url' => $this->confirmation_pdf_url]]
                : [],
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
