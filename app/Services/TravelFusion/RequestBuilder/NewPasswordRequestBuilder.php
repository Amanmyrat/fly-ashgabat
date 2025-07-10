<?php

namespace App\Services\TravelFusion\RequestBuilder;

class NewPasswordRequestBuilder
{
    protected string $username;
    protected string $currentPassword;
    protected string $newPassword;

    public function __construct(string $username, string $currentPassword, string $newPassword)
    {
        $this->username = $username;
        $this->currentPassword = $currentPassword;
        $this->newPassword = $newPassword;
    }

    public function build(): array
    {
        return [
            'NewPassword' => [
                'Username' => $this->username,
                'PasswordCurrent' => $this->currentPassword,
                'PasswordNew' => $this->newPassword,
            ],
        ];
    }
}
