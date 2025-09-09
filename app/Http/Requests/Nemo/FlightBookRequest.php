<?php

namespace App\Http\Requests\Nemo;

use App\Enum\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FlightBookRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'flight_id' => ['required'],

            'payment_type' => [
                'required',
                'string',
                Rule::in([PaymentType::BALANCE->value, PaymentType::POST_PAY->value, PaymentType::STRIPE->value]),
            ],
            'selected_tariff' => [
                'string',
            ],
            'travellers' => [
                'required',
                'array',
                'min:1',
            ],
            'travellers.*.birthdate' => [
                'required',
                'date',
                'before:today',
            ],
            'travellers.*.nationality' => [
                'required',
                'string',
                'size:2',
            ],
            'travellers.*.passport_number' => [
                'required',
                'string',
                'max:50',
            ],
            'travellers.*.passport_expiry_date' => [
                'required',
                'date',
                'after:today',
            ],
            'travellers.*.passport_country' => [
                'required',
                'string',
                'size:2',
            ],
            'travellers.*.firstname' => [
                'required',
                'string',
                'max:100',
            ],
            'travellers.*.lastname' => [
                'required',
                'string',
                'max:100',
            ],
            'travellers.*.gender' => [
                'required',
                'string',
                Rule::in(['male', 'female']),
            ],
            'contact_details' => [
                'required',
                'array',
            ],
            'contact_details.email' => [
                'required',
                'email',
                'max:100',
            ],
            'contact_details.phone.code' => [
                'required',
            ],
            'contact_details.phone.number' => [
                'required',
            ],
        ];
    }
}
