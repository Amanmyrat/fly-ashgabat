<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HotelSearchRequest extends FormRequest
{
    public function rules(): array
    {
        $dateRule = ['required', 'date', 'date_format:Y-m-d'];
        $paymentTypes = ['pay_now', 'pay_deposit', 'pay_on_site'];

        return [
            /**
             * Region ID from regions table.
             * @example 421
             */
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            /**
             * Check-in date (Y-m-d).
             * @example 2026-05-01
             */
            'checkin' => $dateRule,
            /**
             * Check-out date (Y-m-d).
             * @example 2026-05-05
             */
            'checkout' => [...$dateRule, 'after:checkin'],
            /**
             * List of rooms with guests (adults, child ages).
             * @example [{"adults": 2, "child_ages": []}]
             */
            'guests' => ['required', 'array', 'min:1', 'max:9'],
            'guests.*.adults' => ['required', 'integer', 'min:1', 'max:6'],
            'guests.*.child_ages' => ['nullable', 'array'],
            'guests.*.child_ages.*' => ['integer', 'min:0', 'max:17'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filters' => ['nullable', 'array'],
            'filters.min_price' => ['nullable', 'numeric', 'min:0'],
            'filters.max_price' => ['nullable', 'numeric', 'min:0'],
            'filters.property_types' => ['nullable', 'array'],
            'filters.property_types.*' => ['string', 'max:80'],
            'filters.min_distance_km' => ['nullable', 'numeric', 'min:0'],
            'filters.max_distance_km' => ['nullable', 'numeric', 'min:0'],
            'filters.amenities' => ['nullable', 'array'],
            'filters.amenities.*' => ['string', 'max:120'],
            'filters.stars' => ['nullable', 'array'],
            'filters.stars.*' => ['integer', 'min:0', 'max:5'],
            'filters.min_review_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'filters.max_review_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'filters.has_breakfast' => ['sometimes', 'boolean'],
            'filters.has_free_cancellation' => ['sometimes', 'boolean'],
            'filters.payment_types' => ['nullable', 'array'],
            'filters.payment_types.*' => ['string', Rule::in($paymentTypes)],
            'sort_by' => ['sometimes', 'string', Rule::in([
                'price_asc',
                'price_desc',
                'rating_desc',
                'rating_asc',
                'distance_asc',
                'distance_desc',
                'stars_desc',
                'stars_asc',
            ])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            $min = $this->input('filters.min_price');
            $max = $this->input('filters.max_price');
            if (is_numeric($min) && is_numeric($max) && (float) $min > (float) $max) {
                $validator->errors()->add('filters.min_price', 'The min price must be less than or equal to the max price.');
            }

            $minR = $this->input('filters.min_review_score');
            $maxR = $this->input('filters.max_review_score');
            if (is_numeric($minR) && is_numeric($maxR) && (float) $minR > (float) $maxR) {
                $validator->errors()->add('filters.min_review_score', 'The min review score must be less than or equal to the max review score.');
            }

            $minD = $this->input('filters.min_distance_km');
            $maxD = $this->input('filters.max_distance_km');
            if (is_numeric($minD) && is_numeric($maxD) && (float) $minD > (float) $maxD) {
                $validator->errors()->add('filters.min_distance_km', 'The min distance must be less than or equal to the max distance.');
            }
        });
    }
}
