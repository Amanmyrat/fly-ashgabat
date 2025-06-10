<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $flight_booking_id
 * @property string $gender
 * @property string $firstname
 * @property string $lastname
 * @property array $address
 * @property array $phone
 * @property string $email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FlightBooking $flightBooking
 * @method static Builder<static>|ContactDetail newModelQuery()
 * @method static Builder<static>|ContactDetail newQuery()
 * @method static Builder<static>|ContactDetail query()
 * @method static Builder<static>|ContactDetail whereAddress($value)
 * @method static Builder<static>|ContactDetail whereCreatedAt($value)
 * @method static Builder<static>|ContactDetail whereEmail($value)
 * @method static Builder<static>|ContactDetail whereFirstname($value)
 * @method static Builder<static>|ContactDetail whereFlightBookingId($value)
 * @method static Builder<static>|ContactDetail whereGender($value)
 * @method static Builder<static>|ContactDetail whereId($value)
 * @method static Builder<static>|ContactDetail whereLastname($value)
 * @method static Builder<static>|ContactDetail wherePhone($value)
 * @method static Builder<static>|ContactDetail whereUpdatedAt($value)
 * @mixin Eloquent
 */
class ContactDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'flight_booking_id',
        'gender',
        'firstname',
        'lastname',
        'address',
        'phone',
        'email',
    ];

    protected $casts = [
        'address' => 'array',
        'phone' => 'array',
    ];

    public function flightBooking(): BelongsTo
    {
        return $this->belongsTo(FlightBooking::class);
    }
}
