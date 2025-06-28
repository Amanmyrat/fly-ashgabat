<?php

namespace App\Http\Requests;

use App\Enum\FlightType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class XMLAgencyFlightSearchRequest extends FormRequest
{
    protected const DATE_FORMAT = 'Y-m-d';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'departure_code' => [
                'required',
                'string',
                'max:3',
                'min:3',
                'uppercase',
            ],
            'arrival_code' => [
                'required',
                'string',
                'max:3',
                'min:3',
                'uppercase',
                'different:departure_code',
            ],
            'departure_date' => [
                'required',
                'date',
                'date_format:' . self::DATE_FORMAT,
                'after_or_equal:today'
            ],
            'arrival_date' => [
                'nullable',
                'date',
                'date_format:' . self::DATE_FORMAT,
                'after_or_equal:departure_date',
                'required_if:flight_type,' . FlightType::ROUND_TRIP->value
            ],
            'flight_type' => [
                'required',
                'string',
                Rule::enum(FlightType::class)
            ],
            'adults_count' => [
                'required',
                'integer',
                'min:1',
                'max:9'
            ],
            'children_count' => [
                'nullable',
                'integer',
                'min:0',
                'max:9'
            ],
            'infants_count' => [
                'nullable',
                'integer',
                'min:0',
                'max:' . $this->input('adults_count', 0),
            ],
            'class_type' => [
                'required',
                'string',
                Rule::in(['economy', 'business', 'first'])
            ],
        ];
    }
}
