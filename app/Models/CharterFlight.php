<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharterFlight extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_from_id',
        'city_to_id',
        'departure_datetime',
        'price',
    ];

    protected $casts = [
        'departure_datetime' => 'datetime',
        'price' => 'decimal:2',
    ];

    public function cityFrom()
    {
        return $this->belongsTo(City::class, 'city_from_id');
    }

    public function cityTo()
    {
        return $this->belongsTo(City::class, 'city_to_id');
    }
} 