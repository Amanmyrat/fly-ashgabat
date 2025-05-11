<?php

namespace App\Notifications;

use App\Models\TravelFusionPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TravelFusionPasswordExpiring extends Notification
{
    use Queueable;

    public function __construct(protected TravelFusionPassword $passwordChange)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $daysRemaining = now()->diffInDays($this->passwordChange->expires_at, false);

        return (new MailMessage)
            ->subject('TravelFusion Password Expiring Soon')
            ->line("The TravelFusion API password for username {$this->passwordChange->username} will expire in {$daysRemaining} days.")
            ->line('Please change the password through the admin panel to prevent service interruption.')
            ->action('Go to Admin Panel', url('/admin/travel-fusion-passwords'))
            ->line('If you do not change the password before it expires, the account will be deactivated.');
    }
} 