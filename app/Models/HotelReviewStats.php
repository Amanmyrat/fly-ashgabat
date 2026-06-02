<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelReviewStats extends Model
{
    protected $table = 'hotel_review_stats';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $primaryKey = 'hotel_id';

    protected $fillable = [
        'hotel_id',
        'reviews_count',
        'avg_rating',
        'score_cleanness',
        'score_location',
        'score_price',
        'score_services',
        'score_room',
        'score_meal',
        'score_wifi',
        'score_hygiene',
    ];

    protected $casts = [
        'hotel_id'        => 'integer',
        'reviews_count'   => 'integer',
        'avg_rating'      => 'float',
        'score_cleanness' => 'float',
        'score_location'  => 'float',
        'score_price'     => 'float',
        'score_services'  => 'float',
        'score_room'      => 'float',
        'score_meal'      => 'float',
        'score_wifi'      => 'float',
        'score_hygiene'   => 'float',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class, 'hotel_id', 'hid');
    }
}
