<?php

namespace App\Models;

use App\Enum\FlightSupplier;
use App\Services\FlightMarkupService;
use Illuminate\Database\Eloquent\Model;

class FlightMarkup extends Model
{
    protected $fillable = [
        'supplier',
        'airline_code',
        'departure_code',
        'arrival_code',
        'markup_percentage',
        'is_active',
    ];

    protected $casts = [
        'supplier' => FlightSupplier::class,
        'markup_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function (FlightMarkup $markup) {
            $markup->airline_code = $markup->airline_code
                ? strtoupper($markup->airline_code)
                : null;

            $markup->departure_code = $markup->departure_code
                ? strtoupper($markup->departure_code)
                : null;

            $markup->arrival_code = $markup->arrival_code
                ? strtoupper($markup->arrival_code)
                : null;
        });

        static::created(function () {
            app(FlightMarkupService::class)->clearCache();
        });

        static::updated(function () {
            app(FlightMarkupService::class)->clearCache();
        });

        static::deleted(function () {
            app(FlightMarkupService::class)->clearCache();
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSupplier($query, FlightSupplier $supplier)
    {
        return $query->where('supplier', $supplier);
    }

    public function scopeForAirline($query, ?string $airlineCode = null)
    {
        return $query->where('airline_code', $airlineCode);
    }

    public function scopeForRoute($query, ?string $departureCode = null, ?string $arrivalCode = null)
    {
        return $query
            ->where('departure_code', $departureCode)
            ->where('arrival_code', $arrivalCode);
    }

    public function scopeDefaultRoute($query)
    {
        return $query
            ->whereNull('departure_code')
            ->whereNull('arrival_code');
    }
}
