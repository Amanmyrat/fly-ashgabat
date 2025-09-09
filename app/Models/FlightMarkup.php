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
        // Clear cache whenever markup data is changed
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

    public function scopeForAirline($query, string $airlineCode = null)
    {
        return $query->where('airline_code', $airlineCode);
    }
}
