<?php

namespace App\Console\Commands;

use App\Models\TravelFusionPassword;
use App\Notifications\TravelFusionPasswordExpiring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckTravelFusionPasswordExpiry extends Command
{
    protected $signature = 'travelfusion:check-password-expiry';
    protected $description = 'Check TravelFusion API password expiry and notify if needed';

    private array $notifyEmails = [
        'admin@example.com',
        // Add more email addresses here
    ];

    public function handle(): int
    {
        try {
            // Get the current active password record
            $currentPassword = TravelFusionPassword::where('is_active', true)->first();
            
            if (!$currentPassword) {
                $this->error('No active password record found');
                return 1;
            }

            // Check if password is expiring soon
            if ($currentPassword->isExpiringSoon()) {
                $this->info('Password is expiring soon, sending notifications...');
                
                // Send notifications to all configured emails
                foreach ($this->notifyEmails as $email) {
                    Notification::route('mail', $email)
                        ->notify(new TravelFusionPasswordExpiring($currentPassword));
                }

                $this->info('Notifications sent successfully');
                Log::info('TravelFusion password expiry notifications sent', [
                    'username' => $currentPassword->username,
                    'expires_at' => $currentPassword->expires_at,
                    'notified_emails' => $this->notifyEmails,
                ]);
            } else {
                $this->info('Password is not expiring soon');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to check password expiry: ' . $e->getMessage());
            Log::error('Failed to check TravelFusion password expiry', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
} 