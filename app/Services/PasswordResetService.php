<?php

namespace App\Services;

use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordResetService
{
    /**
     * @param $email
     */
    public function sendResetEmail($email): void
    {
        $code = $this->generateResetCode($email);

        Mail::send('emails.reset', ['code' => $code], function ($message) use ($email) {
            $message->to($email);
            $message->subject('Your Kupi Password Reset Code');
        });
    }

    /**
     * @param $email
     * @return int
     */
    private function generateResetCode($email): int
    {
        $code = mt_rand(10000, 99999);

        PasswordResetToken::create([
            'email' => $email,
            'token' => $code
        ]);

        return $code;
    }

    /**
     * @param $email
     * @param $code
     * @return bool
     */
    public function verifyResetCode($email, $code): bool
    {
        $reset = PasswordResetToken::where('email', $email)
            ->where('token', $code)
            ->first();

        if (!$reset || $reset->created_at->addMinutes(30) < now()) {
            return false;
        }

        return true;
    }

    /**
     * @param $email
     * @param $newPassword
     */
    public function resetUserPassword($email, $newPassword)
    {
        $user = User::firstWhere('email', $email);
        $user->password = Hash::make($newPassword);
        $user->email_verified_at = now();
        $user->save();

        PasswordResetToken::where('email', $email)->delete();
    }
}
