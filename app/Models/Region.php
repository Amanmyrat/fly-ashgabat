<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $table = 'regions';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'name_en',
        'name_ru',
        'country_name_en',
        'country_name_ru',
        'type',
        'country_code',
        'latitude',
        'longitude',
        'iata',
    ];

    protected $casts = [
        'id'        => 'integer',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function hotels(): HasMany
    {
        return $this->hasMany(Hotel::class, 'region_id', 'id');
    }
}
