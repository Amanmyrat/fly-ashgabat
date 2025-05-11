<?php

namespace App\Http\Requests;

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
        $routingId = $this->input('routing_id');
        $flightType = Cache::get('routing_' . $routingId);

        if (!$flightType) {
            abort(400, 'The search request has timed out. Please start a new search.');
        }

        $rules = [
            'routing_id' => [
                'required',
                'string',
            ],
            'outward_id' => [
                'required',
                'string',
            ],
            'return_id' => [
                'nullable',
                'string',
            ],
            'payment_type' => [
                'required',
                'string',
                Rule::in(['balance', 'post-pay']),
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
            'travellers.*.age' => [
                'required',
                'integer',
                'min:0',
                'max:120',
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
            'travellers.*.nationality' => [
                'required',
                'string',
                'size:2',
            ],
            'travellers.*.firstname' => [
                'required',
                'string',
                'max:100',
            ],
            'travellers.*.middlename' => [
                'nullable',
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
            'contact_details.gender' => [
                'required',
                'string',
                Rule::in(['male', 'female']),
            ],
            'contact_details.firstname' => [
                'required',
                'string',
                'max:100',
            ],
            'contact_details.middlename' => [
                'nullable',
                'string',
                'max:100',
            ],
            'contact_details.lastname' => [
                'required',
                'string',
                'max:100',
            ],
            'contact_details.address.street' => [
                'required',
                'string',
                'max:100',
            ],
            'contact_details.address.city' => [
                'required',
                'string',
                'max:100',
            ],
            'contact_details.address.country_code' => [
                'required',
                'string',
                'size:2',
            ],
            'contact_details.phone.code' => [
                'required',
            ],
            'contact_details.phone.number' => [
                'required',
            ],
            'contact_details.email' => [
                'required',
                'email',
                'max:100',
            ],
            'options' => ['nullable', 'array'],
            'meta' => [
                'required',
                'array',
            ],
            'meta.end_user_ip_address' => [
                'required',
                'string',
                'ipv4',
            ],
            'meta.end_user_browser_agent' => [
                'required',
                'string',
            ],
            'meta.end_user_device_mac_address' => [
                'nullable',
                'string',
                'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
            ],
        ];

        if ($flightType === 'round-trip') {
            $rules['return_id'][] = 'required';
        }

        return $rules;
    }
}
