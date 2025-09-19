<?php

namespace App\Http\Requests\Tfusion;

use App\Models\FlightBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartBookingRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'booking_reference' => [
                'required',
                Rule::exists('flight_bookings', 'booking_reference'),
            ],
            'session_id' => 'nullable|string', // Required for Stripe payments
        ];
    }

    /**
     * Get the booking model from the validated data.
     */
    public function getBooking(): ?FlightBooking
    {
        return FlightBooking::where('booking_reference', $this->validated('booking_reference'))->first();
    }
}
