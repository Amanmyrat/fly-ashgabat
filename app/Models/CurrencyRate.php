<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CurrencyRate extends Model
{
    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get only active currency rates
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by from currency
     */
    public function scopeFromCurrency(Builder $query, string $currency): Builder
    {
        return $query->where('from_currency', $currency);
    }

    /**
     * Scope to filter by to currency
     */
    public function scopeToCurrency(Builder $query, string $currency): Builder
    {
        return $query->where('to_currency', $currency);
    }

    /**
     * Get the latest active rate for RUB to USD conversion
     */
    public static function getLatestRubToUsdRate(): ?float
    {
        $rate = static::active()
            ->fromCurrency('RUB')
            ->toCurrency('USD')
            ->latest()
            ->first();

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * Convert amount from RUB to USD using the latest rate
     */
    public static function convertRubToUsd(float $rubAmount): ?float
    {
        $rate = static::getLatestRubToUsdRate();
        
        return $rate ? round($rubAmount * $rate, 2) : null;
    }
}
