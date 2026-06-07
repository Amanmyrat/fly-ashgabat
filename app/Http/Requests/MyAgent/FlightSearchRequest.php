<?php

namespace App\Http\Requests\MyAgent;

use App\Enum\FlightType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FlightSearchRequest extends FormRequest
{
    protected const DATE_FORMAT = 'Y-m-d';

    public function rules(): array
    {
        return [
            'departure_code' => [
                'required',
                'string',
                'size:3',
                'uppercase',
            ],
            'arrival_code' => [
                'required',
                'string',
                'size:3',
                'uppercase',
                'different:departure_code',
            ],
            'departure_date' => [
                'required',
                'date',
                'date_format:' . self::DATE_FORMAT,
                'after_or_equal:today',
            ],
            'arrival_date' => [
                'nullable',
                'date',
                'date_format:' . self::DATE_FORMAT,
                'after_or_equal:departure_date',
                'required_if:flight_type,' . FlightType::ROUND_TRIP->value,
            ],
            'flight_type' => [
                'required',
                'string',
                Rule::enum(FlightType::class),
            ],
            'adults_count' => [
                'required',
                'integer',
                'min:1',
                'max:9',
            ],
            'children_count' => [
                'nullable',
                'integer',
                'min:0',
                'max:9',
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
                Rule::in(['all', 'economy', 'business', 'first', 'premium_economy']),
            ],

            // Backend filtering/sorting, optional.
            'filters' => [
                'nullable',
                'array',
            ],
            'filters.airlines' => [
                'nullable',
                'array',
            ],
            'filters.airlines.*' => [
                'string',
                'size:2',
                'uppercase',
            ],
            'filters.stops' => [
                'nullable',
                'integer',
                'min:0',
                'max:5',
            ],
            'filters.baggage_included' => [
                'nullable',
                'boolean',
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'default',
                    'price',
                    '-price',
                    'duration',
                    '-duration',
                    'departure_time',
                    '-departure_time',
                ]),
            ],
            'count' => [
                'nullable',
                'integer',
                'min:1',
                'max:200',
            ],
            'is_direct_only' => [
                'nullable',
                'boolean',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
