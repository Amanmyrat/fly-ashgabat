<?php

namespace App\Services\TravelFusion\Requests;

class LoginRequestBuilder
{
    protected string $username;
    protected string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function build(): array
    {
        return [
            'Login' => [
                'Username' => $this->username,
                'Password' => $this->password,
            ],
        ];
    }
}
