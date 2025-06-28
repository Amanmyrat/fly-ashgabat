<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FlightSupplier: string implements HasLabel, HasColor
{
    case TFUSION = 'TFusion';
    case XMLAGENCY = 'XMLAgency';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TFUSION => 'Travel Fusion',
            self::XMLAGENCY => 'XML Agency',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::TFUSION => 'info',
            self::XMLAGENCY => 'warning',
        };
    }
} 