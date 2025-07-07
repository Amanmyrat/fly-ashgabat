<?php

namespace App\Http\Requests\XMLAgency;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Http\FormRequest;

class FlightProcessDetailsRequest extends FormRequest
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

        return [
            'search_guid' => [
                'required',
                'string',
            ],
            'offer_code' => [
                'required',
                'string',
            ],
        ];
    }
}
