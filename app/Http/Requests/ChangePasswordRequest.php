<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use JetBrains\PhpStorm\ArrayShape;

class ChangePasswordRequest  extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    #[ArrayShape(['old_password' => "string", 'new_password' => "string"])]
    public function rules(): array
    {
        return [
            'old_password' => 'required',
            'new_password' => 'required|min:8|regex:/[a-zA-Z]/|regex:/[0-9]/',
        ];
    }
}
