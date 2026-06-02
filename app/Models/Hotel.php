<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hotel extends Model
{
    protected $table = 'hotels';

    protected $primaryKey = 'hid';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'hid',
        'etg_id',
        'name_en',
        'name_ru',
        'region_id',
        'country_code',
        'latitude',
        'longitude',
        'star_rating',
        'kind',
        'address_en',
        'address_ru',
        'check_in_time',
        'check_out_time',
        'images',
        'serp_filters',
    ];

    protected $casts = [
        'hid'          => 'integer',
        'region_id'    => 'integer',
        'latitude'     => 'float',
        'longitude'    => 'float',
        'star_rating'  => 'integer',
        'images'       => 'array',
        'serp_filters' => 'array',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(HotelReview::class, 'hotel_id', 'hid');
    }

    public function reviewStats(): HasOne
    {
        return $this->hasOne(HotelReviewStats::class, 'hotel_id', 'hid');
    }
}
