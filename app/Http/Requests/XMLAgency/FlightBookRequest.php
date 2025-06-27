<?php

namespace App\Http\Requests\XMLAgency;

use App\Enum\PaymentType;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Http\FormRequest;

class FlightBookRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $searchGuid = $this->input('search_guid');

        // Check if search session exists in XMLAgency format
        if ($searchGuid && !Cache::has('search_guid' . $searchGuid)) {
            abort(400, 'The search session has expired. Please start a new search.');
        }

        $rules = [
            'search_guid' => [
                'required',
                'string',
            ],
            'offer_code' => [
                'required',
                'string',
            ],
            'payment_type' => [
                'required',
                'string',
                Rule::in([PaymentType::BALANCE->value, PaymentType::POST_PAY->value, PaymentType::STRIPE->value]),
            ],
            // XMLAgency passenger data (minimal requirements)
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
                'size:3', // XMLAgency uses 3-letter ISO codes (RUS, USA, etc.)
            ],
            'travellers.*.passport_number' => [
                'required',
                'string',
                'max:50',
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
            // XMLAgency contact details (minimal - just email and phone)
            'contact_details' => [
                'required',
                'array',
            ],
            'contact_details.email' => [
                'required',
                'email',
                'max:100',
            ],
            'contact_details.phone' => [
                'required',
                'string',
                'max:20', // Full phone number with country code
            ],
        ];

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'search_guid.required' => 'Search session is required.',
            'offer_code.required' => 'Flight offer selection is required.',
            'payment_type.required' => 'Payment method is required.',
            'payment_type.in' => 'Invalid payment method selected.',
            'travellers.required' => 'At least one traveller is required.',
            'travellers.min' => 'At least one traveller is required.',
            'contact_details.required' => 'Contact details are required.',
            'contact_details.email.required' => 'Email address is required.',
            'contact_details.phone.required' => 'Phone number is required.',
        ];
    }
}
