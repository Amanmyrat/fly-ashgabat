<?php

namespace App\Http\Requests\MyAgent;

use Illuminate\Foundation\Http\FormRequest;

class FlightPickRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Flight id is required.',
        ];
    }
}
