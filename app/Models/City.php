<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class City extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'name',
        'code',
    ];

    protected $translatable = ['name'];

    protected $casts = [
        'name' => 'array',
    ];

    public function charterFlightsFrom()
    {
        return $this->hasMany(CharterFlight::class, 'city_from_id');
    }

    public function charterFlightsTo()
    {
        return $this->hasMany(CharterFlight::class, 'city_to_id');
    }
} 