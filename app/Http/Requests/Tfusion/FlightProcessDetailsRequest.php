<?php

namespace App\Http\Requests\Tfusion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class FlightProcessDetailsRequest extends FormRequest
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
        ];

        if ($flightType === 'round-trip') {
            $rules['return_id'][] = 'required';
        }

        return $rules;
    }
}
