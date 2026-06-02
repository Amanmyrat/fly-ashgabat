<?php

namespace App\DTO;

final readonly class FirstRateDTO
{
    public function __construct(
        public ?float $amount,
        public string $currency,
        public ?string $roomName = null,
        public ?string $beddingType = null,
        public ?int $allotment = null,
        public ?bool $hasBreakfast = null,
        public ?string $freeCancellationBefore = null,
        public ?string $paymentType = null,
        public ?string $matchHash = null,
        public ?string $searchHash = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'amount'                   => $this->amount,
            'currency'                 => $this->currency,
            'room_name'                => $this->roomName,
            'bedding_type'             => $this->beddingType,
            'allotment'                => $this->allotment,
            'has_breakfast'            => $this->hasBreakfast,
            'free_cancellation_before' => $this->freeCancellationBefore,
            'payment_type'             => $this->paymentType,
            'match_hash'               => $this->matchHash,
            'search_hash'              => $this->searchHash,
        ], fn ($v) => $v !== null);
    }
}
