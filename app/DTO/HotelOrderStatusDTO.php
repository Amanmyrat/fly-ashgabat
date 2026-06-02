<?php

namespace App\DTO;

final readonly class HotelOrderStatusDTO
{
    // ETG /hotel/order/booking/finish/status/ top-level status values (lowercase)
    private const STATUS_OK         = 'ok';
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_3DS        = '3ds';
    private const STATUS_ERROR      = 'error';

    /**
     * @param  array<string, mixed>|null  $data3ds
     * @param  array<string, mixed>       $raw
     */
    public function __construct(
        public string  $partnerOrderId,
        public string  $status,
        public ?array  $data3ds,
        public array   $raw,
    ) {}

    /**
     * Build from the full ETG /hotel/order/booking/finish/status/ response.
     *
     * ETG places `status` at the top level of the response envelope, not inside `data`:
     * { "status": "ok", "error": null, "data": { "partner_order_id": "...", ... } }
     *
     * @param  array<string, mixed>  $response  Full ETG response (as returned by EtgClient::post)
     */
    public static function fromEtgResponse(array $response): self
    {
        $data = $response['data'] ?? [];

        return new self(
            partnerOrderId: (string) ($data['partner_order_id'] ?? ''),
            status:         strtolower((string) ($response['status'] ?? 'error')),
            data3ds:        !empty($data['data_3ds']) && is_array($data['data_3ds'])
                                ? $data['data_3ds']
                                : null,
            raw:            $response,
        );
    }

    /** Booking successfully confirmed. */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    /** Booking is still being processed — keep polling. */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /** 3D Secure check required before booking can complete. */
    public function needs3ds(): bool
    {
        return $this->status === self::STATUS_3DS;
    }

    /** Booking definitively failed. */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }
}
