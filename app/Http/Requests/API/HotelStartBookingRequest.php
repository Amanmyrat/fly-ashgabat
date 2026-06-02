<?php

namespace App\Http\Requests\API;

use App\Models\HotelBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HotelStartBookingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'partner_order_id' => [
                'required',
                Rule::exists('hotel_bookings', 'partner_order_id'),
            ],
            'session_id' => 'nullable|string',
        ];
    }

    /**
     * Get the booking model from the validated data.
     */
    public function getBooking(): ?HotelBooking
    {
        return HotelBooking::where('partner_order_id', $this->validated('partner_order_id'))->first();
    }
}
