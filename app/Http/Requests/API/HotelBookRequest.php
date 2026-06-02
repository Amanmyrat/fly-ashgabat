<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HotelBookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'book_hash' => ['required', 'string'],

            'payment_type' => [
                'required',
                'string',
                Rule::in(auth('sanctum')->check() ? ['balance', 'postpay', 'stripe'] : ['postpay', 'stripe']),
            ],


            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.guests' => ['required', 'array', 'min:1'],

            'rooms.*.guests.*.first_name' => [
                'nullable',
                'string',
                'min:1',
                'max:50',
                'regex:/^[\pL\s\'\-,\.]+$/u',
            ],
            'rooms.*.guests.*.last_name' => [
                'nullable',
                'string',
                'min:1',
                'max:50',
                'regex:/^[\pL\s\'\-,\.]+$/u',
            ],
            'contact.email' => ['required', 'email', 'max:255'],
            'contact.phone' => ['required', 'string', 'min:5', 'max:35'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rooms = $this->input('rooms', []);

        foreach ($rooms as $roomIndex => $room) {
            foreach (($room['guests'] ?? []) as $guestIndex => $guest) {
                if (array_key_exists('first_name', $guest) && is_string($guest['first_name'])) {
                    $rooms[$roomIndex]['guests'][$guestIndex]['first_name'] = trim($guest['first_name']);
                }

                if (array_key_exists('last_name', $guest) && is_string($guest['last_name'])) {
                    $rooms[$roomIndex]['guests'][$guestIndex]['last_name'] = trim($guest['last_name']);
                }
            }
        }

        $this->merge([
            'rooms' => $rooms,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('rooms', []) as $roomIndex => $room) {
                $hasNamedGuest = false;

                foreach ($room['guests'] ?? [] as $guestIndex => $guest) {
                    $firstName = trim((string) ($guest['first_name'] ?? ''));
                    $lastName = trim((string) ($guest['last_name'] ?? ''));

                    if (($firstName !== '' && $lastName === '') || ($firstName === '' && $lastName !== '')) {
                        $validator->errors()->add(
                            "rooms.$roomIndex.guests.$guestIndex",
                            'Both first name and last name are required when providing guest name.'
                        );
                    }

                    if ($firstName !== '' && $lastName !== '') {
                        $hasNamedGuest = true;
                    }
                }

                if (!$hasNamedGuest) {
                    $validator->errors()->add(
                        "rooms.$roomIndex.guests",
                        'At least one guest first name and last name is required in each booked room.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'rooms.*.guests.*.first_name.regex' => 'First name may only contain letters, spaces, apostrophes, hyphens, commas, and dots.',
            'rooms.*.guests.*.last_name.regex' => 'Last name may only contain letters, spaces, apostrophes, hyphens, commas, and dots.',
        ];
    }
}
