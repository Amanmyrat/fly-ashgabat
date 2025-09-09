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
    
    protected $attributes = [
        'from_currency' => 'USD',
        'to_currency' => 'RUB',
        'is_active' => true,
    ];

    /**
     * Supported target currencies for USD conversion
     */
    public const SUPPORTED_CURRENCIES = [
        'RUB' => 'Russian Ruble',
        'KZT' => 'Kazakhstani Tenge',
        'UZS' => 'Uzbekistani Som',
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
     * Looks for USD->RUB rates and converts them to RUB->USD
     */
    public static function getLatestRubToUsdRate(): ?float
    {
        // Look for USD to RUB rates (e.g., 1 USD = 83 RUB)
        $rate = static::active()
            ->fromCurrency('USD')
            ->toCurrency('RUB')
            ->latest()
            ->first();

        // Convert USD->RUB rate to RUB->USD rate (1/rate)
        return $rate && $rate->rate > 0 ? (1 / (float) $rate->rate) : null;
    }

    /**
     * Convert amount from RUB to USD using the latest rate
     * Rate is stored as: 1 USD = X RUB (e.g., 1 USD = 83 RUB)
     * So to convert RUB to USD: rubAmount / originalRate
     */
    public static function convertRubToUsd(float $rubAmount): ?float
    {
        $rate = static::getLatestRubToUsdRate();
        
        return $rate ? round($rubAmount * $rate, 2) : null;
    }

    /**
     * Get the latest active rate for any currency to USD conversion
     * Looks for USD->CURRENCY rates and converts them to CURRENCY->USD
     */
    public static function getLatestCurrencyToUsdRate(string $currency): ?float
    {
        $rate = static::active()
            ->fromCurrency('USD')
            ->toCurrency(strtoupper($currency))
            ->latest()
            ->first();

        return $rate && $rate->rate > 0 ? (1 / (float) $rate->rate) : null;
    }

    /**
     * Convert amount from any supported currency to USD using the latest rate
     */
    public static function convertCurrencyToUsd(float $amount, string $currency): ?float
    {
        $rate = static::getLatestCurrencyToUsdRate($currency);
        
        return $rate ? round($amount * $rate, 2) : null;
    }

    /**
     * Get the latest active USD to currency rate
     */
    public static function getLatestUsdToCurrencyRate(string $currency): ?float
    {
        $rate = static::active()
            ->fromCurrency('USD')
            ->toCurrency(strtoupper($currency))
            ->latest()
            ->first();

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * Convert amount from USD to any supported currency using the latest rate
     */
    public static function convertUsdToCurrency(float $usdAmount, string $currency): ?float
    {
        $rate = static::getLatestUsdToCurrencyRate($currency);
        
        return $rate ? round($usdAmount * $rate, 2) : null;
    }

    /**
     * Get currency symbol for display purposes
     */
    public static function getCurrencySymbol(string $currency): string
    {
        return match(strtoupper($currency)) {
            'USD' => '$',
            'RUB' => '₽',
            'KZT' => '₸',
            'UZS' => 'so\'m',
            default => $currency,
        };
    }
}
