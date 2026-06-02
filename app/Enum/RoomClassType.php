<?php

namespace App\Enum;

enum RoomClassType: int
{
    case STANDARD = 1;
    case SUPERIOR = 2;
    case DELUXE = 3;
    case SUITE = 4;
    case PREMIUM = 5;

    public function key(): string
    {
        return match ($this) {
            self::STANDARD => 'standard',
            self::SUPERIOR => 'superior',
            self::DELUXE => 'deluxe',
            self::SUITE => 'suite',
            self::PREMIUM => 'premium',
        };
    }
}
