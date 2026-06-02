<?php

namespace App\Http\Requests\MyAgent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FlightProcessDetailsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'string',
            ],
            'operation' => [
                'nullable',
                'string',
                Rule::in(['All', 'GetFareFamilies', 'GetFareRules']),
            ],
        ];
    }
}
