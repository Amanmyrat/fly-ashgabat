<?php

namespace App\DTO;

final readonly class HotelBookingFormResultDTO
{
    /**
     * @param  array<int, array{type: string, amount: string, currency_code: string, is_need_credit_card_data: bool, is_need_cvc: bool}>  $paymentTypes
     */
    public function __construct(
        public int    $orderId,
        public string $partnerOrderId,
        public ?int   $itemId,
        public array  $paymentTypes,
        public bool   $isGenderSpecificationRequired,
    ) {}

    /**
     * Build from the ETG /hotel/order/booking/form/ response `data` block.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromEtgResponse(array $data): self
    {
        return new self(
            orderId:                       (int)    ($data['order_id']               ?? 0),
            partnerOrderId:                (string) ($data['partner_order_id']        ?? ''),
            itemId:                        isset($data['item_id']) ? (int) $data['item_id'] : null,
            paymentTypes:                  (array)  ($data['payment_types']           ?? []),
            isGenderSpecificationRequired: (bool)   ($data['is_gender_specification_required'] ?? false),
        );
    }
}
