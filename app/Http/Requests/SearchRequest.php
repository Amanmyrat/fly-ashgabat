<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $allowedSortOptions = [
            'default',
            'price',
            '-price',
            'duration',
            '-duration',
            'departure_time',
            '-departure_time',
        ];
        $allowedSortOptionsString = implode(',', $allowedSortOptions);

        return [
            'departure_city_code' => 'required|string|max:3',
            'arrival_city_code' => 'required|string|max:3',
            'departure_date_from' => 'required|date|after_or_equal:today',
            'departure_date_to' => 'nullable|date|after_or_equal:departure_date_from',
            'adults_count' => 'required|integer|min:1',
            'childs_count' => 'required|integer',
            'baby_without_a_seat' => 'required|integer|lte:adults_count',
            'baby_with_seat' => 'required|integer',
            'class_type' => 'string|in:Economy,Business,First,All',
            'filters' => 'sometimes|array',
            'filters.baggage_included' => 'sometimes|boolean',
            'filters.min_price' => 'sometimes|integer',
            'filters.max_price' => 'sometimes|integer',
            'filters.airlines' => 'sometimes|array',
            'filters.airlines.*' => 'sometimes|string',
            'filters.stops' => 'sometimes|integer|min:0',
            'sort' => ['filled', 'in:'.$allowedSortOptionsString],
        ];
    }
}
