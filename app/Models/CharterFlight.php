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
        'departure_weekday',
        'departure_time',
        'layover_hours',
        'layover_minutes',
        'arrival_weekday',
        'arrival_time',
        'price',
    ];

    protected $casts = [
        'departure_time' => 'datetime:H:i',
        'arrival_time' => 'datetime:H:i',
        'layover_hours' => 'integer',
        'layover_minutes' => 'integer',
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

    /**
     * Get available weekdays for charter flights
     */
    public static function getWeekdays(): array
    {
        return [
            'Monday' => 'Monday',
            'Tuesday' => 'Tuesday',
            'Wednesday' => 'Wednesday',
            'Thursday' => 'Thursday',
            'Friday' => 'Friday',
            'Saturday' => 'Saturday',
            'Sunday' => 'Sunday',
        ];
    }

    /**
     * Get formatted departure time and weekday
     */
    public function getFormattedDepartureAttribute(): string
    {
        return $this->departure_weekday . ' at ' . $this->departure_time->format('H:i');
    }

    /**
     * Get formatted arrival time and weekday
     */
    public function getFormattedArrivalAttribute(): string
    {
        return $this->arrival_weekday . ' at ' . $this->arrival_time->format('H:i');
    }

    /**
     * Get formatted layover duration
     */
    public function getFormattedLayoverAttribute(): string
    {
        $hours = $this->layover_hours;
        $minutes = $this->layover_minutes;
        
        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } elseif ($minutes > 0) {
            return "{$minutes}m";
        }
        
        return '0m';
    }
} 