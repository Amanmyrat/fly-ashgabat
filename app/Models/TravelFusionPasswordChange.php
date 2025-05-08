<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelFusionPasswordChange extends Model
{
    protected $fillable = [
        'username',
        'password',
        'changed_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function isExpiringSoon(): bool
    {
        return now()->addDays(15)->isAfter($this->expires_at);
    }
} 