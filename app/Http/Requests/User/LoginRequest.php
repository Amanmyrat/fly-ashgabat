<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use JetBrains\PhpStorm\ArrayShape;

class LoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    #[ArrayShape(['email' => "string", 'password' => "string"])]
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            /**
             * Admin password.
             *
             * @var string
             *
             * @example "Aa12345678"
             */
            'password' => 'required',
        ];
    }
}
