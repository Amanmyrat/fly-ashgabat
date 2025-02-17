<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentType: string implements HasLabel, HasColor
{
    case POST_PAY = 'post-pay';
    case BALANCE = 'balance';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::POST_PAY  => 'Post pay',
            self::BALANCE  => 'Balance',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::BALANCE => 'success',
            self::POST_PAY => 'warning',
        };
    }
}
