<?php

namespace App\Http\Requests\MyAgent;

use App\Models\FlightBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FlightBookingCancelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'booking_reference' => [
                'required',
                Rule::exists('flight_bookings', 'booking_reference'),
            ],
        ];
    }

    public function getBooking(): FlightBooking
    {
        return FlightBooking::where('booking_reference', $this->validated('booking_reference'))->firstOrFail();
    }
}
