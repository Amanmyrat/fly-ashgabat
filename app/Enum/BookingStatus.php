<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasLabel, HasColor
{
    case PENDING = 'Pending';
    case BOOKING_IN_PROGRESS = 'BookingInProgress';
    case SUCCEEDED = 'Succeeded';
    case FAILED = 'Failed';
    case UNCONFIRMED = 'Unconfirmed';
    case UNCONFIRMED_BY_SUPPLIER = 'UnconfirmedBySupplier';
    case CANCELLED = 'Cancelled';
    case DUPLICATE = 'Duplicate';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING  => 'В ожидании',
            self::BOOKING_IN_PROGRESS  => 'В процессе',
            self::SUCCEEDED => 'Одобрено',
            self::FAILED   => 'Неудача',
            self::UNCONFIRMED => 'Отменено',
            self::UNCONFIRMED_BY_SUPPLIER => 'Отменено поставщиком',
            self::CANCELLED => 'Отменено пользователем',
            self::DUPLICATE => 'Дубликат',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::PENDING, self::BOOKING_IN_PROGRESS => 'gray',
            self::SUCCEEDED => 'success',
            self::FAILED   => 'danger',
            self::UNCONFIRMED, self::UNCONFIRMED_BY_SUPPLIER, self::CANCELLED => 'warning',
            self::DUPLICATE => 'info',
        };
    }
}
