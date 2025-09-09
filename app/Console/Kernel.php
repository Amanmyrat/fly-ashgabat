<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * These schedules are used to run console commands.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // ... existing schedules ...

        // Check TravelFusion password expiry daily
        $schedule->command('travelfusion:check-password-expiry')
            ->daily();

        // Schedule the route caching command to run at 1 AM UK time (00:00 UTC)
        $schedule->command('travelfusion:cache-routes')
            ->dailyAt('00:00')
            ->timezone('UTC')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
} 