<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking query()
 * @property int $id
 * @property int|null $user_id
 * @property string $booking_reference
 * @property string|null $supplier_reference
 * @property array $origin
 * @property array $destination
 * @property array $outward
 * @property array|null $return
 * @property array $price
 * @property array $features
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereBookingReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereDestination($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereFeatures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereOrigin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereOutward($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereReturn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereSupplierReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking whereUserId($value)
 * @property string $payment_type
 * @property-read ContactDetail|null $contactDetail
 * @property-read Collection<int, Traveller> $travellers
 * @property-read int|null $travellers_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightBooking wherePaymentType($value)
 * @mixin Eloquent
 */
class FlightBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'booking_reference',
        'supplier_reference',
        'origin',
        'destination',
        'outward',
        'return',
        'price',
        'features',
        'status',
        'payment_type',
    ];

    protected $casts = [
        'origin' => 'array',
        'destination' => 'array',
        'outward' => 'array',
        'return' => 'array',
        'price' => 'array',
        'features' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function travellers(): HasMany
    {
        return $this->hasMany(Traveller::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(FlightTicket::class, 'booking_id');
    }

    public function contactDetail(): HasOne
    {
        return $this->hasOne(ContactDetail::class);
    }
}
