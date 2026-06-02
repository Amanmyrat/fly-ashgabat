<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class HotelPageRequest extends FormRequest
{
    public function rules(): array
    {
        $dateRule = ['required', 'date', 'date_format:Y-m-d'];

        return [
            /**
             * Hotel identifier — numeric hid (e.g. 12345) or ETG string id (e.g. yyldyz).
             * @example "yyldyz"
             */
            'hotel' => ['required', 'string'],
            /**
             * Check-in date (Y-m-d).
             * @example 2026-06-01
             */
            'checkin' => $dateRule,
            /**
             * Check-out date (Y-m-d).
             * @example 2026-06-05
             */
            'checkout' => [...$dateRule, 'after:checkin'],
            /**
             * Rooms with guests.
             * @example [{"adults": 2, "child_ages": []}]
             */
            'guests' => ['required', 'array', 'min:1', 'max:9'],
            'guests.*.adults' => ['required', 'integer', 'min:1', 'max:6'],
            'guests.*.child_ages' => ['nullable', 'array'],
            'guests.*.child_ages.*' => ['integer', 'min:0', 'max:17'],
        ];
    }
}
