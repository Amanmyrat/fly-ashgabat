<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FlightType: string implements HasLabel, HasColor
{
    case ONE_WAY = 'one-way';
    case ROUND_TRIP = 'round-trip';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ONE_WAY => 'One Way',
            self::ROUND_TRIP => 'Round Trip',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::ONE_WAY => 'primary',
            self::ROUND_TRIP => 'success',
        };
    }
} 