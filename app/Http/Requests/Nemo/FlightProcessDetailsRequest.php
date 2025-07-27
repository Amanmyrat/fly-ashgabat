<?php

namespace App\Http\Requests\Nemo;

use App\Enum\FlightType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FlightProcessDetailsRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'flight_id' => 'required|string',
            'operation' => 'required|string|in:GetFareFamilies,ActualizeFlight,GetFareRules',
        ];
    }
}
