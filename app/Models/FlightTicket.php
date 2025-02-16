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
 * @property-read FlightBooking|null $booking
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightTicket newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightTicket newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightTicket query()
 * @property int $id
 * @property int $booking_id
 * @property string $name
 * @property string $ticket_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightTicket whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightTicket whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightTicket whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightTicket whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightTicket whereTicketUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FlightTicket whereUpdatedAt($value)
 * @mixin Eloquent
 */
class FlightTicket extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id', 'name', 'ticket_url'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(FlightBooking::class, 'booking_id');
    }
}
