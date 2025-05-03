<?php

namespace App\Models;

use App\Enum\BookingStatus;
use App\Enum\PaymentType;
use App\Services\AirportLocatorService;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
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
 * @method static Builder<static>|FlightBooking newModelQuery()
 * @method static Builder<static>|FlightBooking newQuery()
 * @method static Builder<static>|FlightBooking query()
 * @property int $id
 * @property int|null $user_id
 * @property string $booking_reference
 * @property string|null $supplier_reference
 * @property array $origin
 * @property array $destination
 * @property array $outward
 * @property array|null $return
 * @property array $price
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|FlightBooking whereBookingReference($value)
 * @method static Builder<static>|FlightBooking whereCreatedAt($value)
 * @method static Builder<static>|FlightBooking whereDestination($value)
 * @method static Builder<static>|FlightBooking whereId($value)
 * @method static Builder<static>|FlightBooking whereOrigin($value)
 * @method static Builder<static>|FlightBooking whereOutward($value)
 * @method static Builder<static>|FlightBooking wherePrice($value)
 * @method static Builder<static>|FlightBooking whereReturn($value)
 * @method static Builder<static>|FlightBooking whereStatus($value)
 * @method static Builder<static>|FlightBooking whereSupplierReference($value)
 * @method static Builder<static>|FlightBooking whereUpdatedAt($value)
 * @method static Builder<static>|FlightBooking whereUserId($value)
 * @property string $payment_type
 * @property-read ContactDetail|null $contactDetail
 * @property-read Collection<int, Traveller> $travellers
 * @property-read int|null $travellers_count
 * @method static Builder<static>|FlightBooking wherePaymentType($value)
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
        'status',
        'payment_type',
    ];

    protected $casts = [
        'origin' => 'array',
        'destination' => 'array',
        'outward' => 'array',
        'return' => 'array',
        'price' => 'array',
        'status' => BookingStatus::class,
        'payment_type' => PaymentType::class
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

    /**
     * Get formatted flight directions based on the direction type.
     *
     * @param AirportLocatorService $airportLocatorService
     * @return string
     */
    public function getFlightDirectionsAndType(AirportLocatorService $airportLocatorService): string
    {
        $originCode = $this->origin['Code'] ?? null;
        $destinationCode = $this->destination['Code'] ?? null;

        $departureCity = $this->getCityNameByCode($originCode, $airportLocatorService);
        $arrivalCity = $this->getCityNameByCode($destinationCode, $airportLocatorService);

        return sprintf(
            '%s - %s',
            $departureCity,
            $arrivalCity,
        );
    }

    /**
     * Get formatted flight directions based on the direction type with date
     *
     * @param AirportLocatorService $airportLocatorService
     * @return string
     */
    public function getFlightDirectionsAndTypeWithDate(AirportLocatorService $airportLocatorService): string
    {
        $originCode = $this->origin['Code'] ?? null;
        $destinationCode = $this->destination['Code'] ?? null;

        $departureDate = $firstSegment['DepatureDateTime'] ?? null;

        $departureCity = $this->getCityNameByCode($originCode, $airportLocatorService);
        $arrivalCity = $this->getCityNameByCode($destinationCode, $airportLocatorService);

        $formattedDepartureDate = $departureDate ? \Carbon\Carbon::parse($departureDate)->format('d.m.Y H:i') : 'Unknown date';

        return sprintf(
            '%s - %s и даты вылета: %s',
            $departureCity,
            $arrivalCity,
            $formattedDepartureDate
        );
    }

    /**
     * Fetch city name by code using the AirportLocatorService.
     *
     * @param string|null $cityCode
     * @param AirportLocatorService $airportLocatorService
     * @return string
     */
    protected function getCityNameByCode(?string $cityCode, AirportLocatorService $airportLocatorService): string
    {
        if (!$cityCode) {
            return 'Unknown';
        }

        $cityInfo = $airportLocatorService->getCityByCode($cityCode);

        return $cityInfo['name']['en'] ?? $cityCode;
    }
}
