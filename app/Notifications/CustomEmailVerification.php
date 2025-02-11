<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class CustomEmailVerification extends VerifyEmail
{
    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->view('emails.activate', ['activationUrl' => $verificationUrl])  // Use your custom Blade view
            ->subject('Подтверждение электронной почты')
            ->greeting('Здравствуйте!')
            ->line('Для подтверждения вашей электронной почты нажмите на кнопку ниже.')
            ->action('Активировать', $verificationUrl)
            ->line('Если вы не создавали аккаунт, просто проигнорируйте это сообщение.');

    }
}
