<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasLabel, HasColor
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in-progress';
    case APPROVED = 'approved';
    case FAILED = 'failed';
    case CANCELED = 'canceled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING  => 'В ожидании',
            self::IN_PROGRESS  => 'В процессе',
            self::APPROVED => 'Одобрено',
            self::FAILED   => 'Неудача',
            self::CANCELED => 'Отменено',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::PENDING, self::IN_PROGRESS => 'gray',
            self::APPROVED => 'success',
            self::FAILED   => 'danger',
            self::CANCELED => 'warning',
        };
    }
}
