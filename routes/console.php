<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('travelfusion:check-password-expiry')->daily();

Schedule::command('travelfusion:cache-routes')
    ->dailyAt('00:00')
    ->timezone('UTC')
    ->withoutOverlapping();

Schedule::command('etg:update-hotel-dump')
    ->dailyAt('02:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('etg:update-region-dump')
    ->dailyAt('02:30')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->runInBackground();
