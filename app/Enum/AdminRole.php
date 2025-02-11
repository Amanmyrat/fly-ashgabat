<?php

namespace App\Enum;

enum AdminRole: string
{
    case SUPER_ADMIN = 'super-admin';
    case OPERATOR = 'operator';

    public function label(): string
    {
        return match($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::OPERATOR => 'Operator',
        };
    }
}
