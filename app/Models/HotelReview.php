<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelReview extends Model
{
    protected $table = 'hotel_reviews';

    protected $fillable = [
        'hotel_id',
        'rating',
        'comment',
        'author_name',
        'room_name',
        'adults',
        'children',
        'traveller_type',
        'trip_type',
        'score_cleanness',
        'score_location',
        'score_price',
        'score_services',
        'score_room',
        'score_meal',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'hotel_id'        => 'integer',
        'rating'          => 'float',
        'adults'          => 'integer',
        'children'        => 'integer',
        'score_cleanness' => 'float',
        'score_location'  => 'float',
        'score_price'     => 'float',
        'score_services'  => 'float',
        'score_room'      => 'float',
        'score_meal'      => 'float',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class, 'hotel_id', 'hid');
    }
}
