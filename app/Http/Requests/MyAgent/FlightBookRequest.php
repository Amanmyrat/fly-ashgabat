<?php

namespace App\Http\Requests\MyAgent;

use App\Enum\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FlightBookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'flight_id' => [
                'required',
                'string',
            ],
            'selected_tariff' => [
                'nullable',
                'string',
            ],
            'health_declaration_accepted' => [
                'nullable',
                'boolean',
            ],
            'payment_type' => [
                'required',
                'string',
                Rule::in([
                    PaymentType::BALANCE->value,
                    PaymentType::POST_PAY->value,
                    PaymentType::STRIPE->value,
                ]),
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
            'travellers.*.middlename' => [
                'nullable',
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
            'contact_details.firstname' => [
                'nullable',
                'string',
                'max:100',
            ],
            'contact_details.lastname' => [
                'nullable',
                'string',
                'max:100',
            ],
            'contact_details.email' => [
                'required',
                'email',
                'max:100',
            ],
            'contact_details.phone.code' => [
                'required',
                'string',
                'max:10',
            ],
            'contact_details.phone.number' => [
                'required',
                'string',
                'max:30',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'flight_id.required' => 'Flight selection is required.',
            'payment_type.required' => 'Payment method is required.',
            'travellers.required' => 'At least one traveller is required.',
            'travellers.min' => 'At least one traveller is required.',
            'contact_details.required' => 'Contact details are required.',
            'contact_details.email.required' => 'Email address is required.',
            'contact_details.phone.code.required' => 'Phone code is required.',
            'contact_details.phone.number.required' => 'Phone number is required.',
        ];
    }
}
