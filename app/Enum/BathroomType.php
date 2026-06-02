<?php

namespace App\Enum;

enum BathroomType: int
{
    case UNKNOWN = 0;
    case SHARED = 1;
    case PRIVATE = 2;

    public function key(): string
    {
        return match ($this) {
            self::UNKNOWN => 'unknown',
            self::SHARED => 'shared_bathroom',
            self::PRIVATE => 'private_bathroom',
        };
    }
}
