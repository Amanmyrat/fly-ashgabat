<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $flight_booking_id
 * @property Carbon $birthdate
 * @property string $passport_number
 * @property Carbon $passport_expiry_date
 * @property string $passport_country
 * @property string $nationality
 * @property string $firstname
 * @property string $lastname
 * @property string $gender
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FlightBooking $flightBooking
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller whereBirthdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller whereFirstname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller whereFlightBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller whereLastname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller whereNationality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller wherePassportCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller wherePassportExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller wherePassportNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Traveller whereUpdatedAt($value)
 * @mixin Eloquent
 */
class Traveller extends Model
{
    use HasFactory;

    protected $fillable = [
        'flight_booking_id',
        'birthdate',
        'passport_number',
        'passport_expiry_date',
        'passport_country',
        'nationality',
        'firstname',
        'lastname',
        'gender',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'passport_expiry_date' => 'date',
    ];

    public function flightBooking(): BelongsTo
    {
        return $this->belongsTo(FlightBooking::class);
    }
}
