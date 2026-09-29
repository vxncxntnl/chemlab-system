<?php

use App\Console\Commands\ExpireActivities;
use App\Console\Commands\SendOverdueReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send overdue reminders every day at 8:00 AM
Schedule::command(SendOverdueReminders::class)->dailyAt('08:00');

// Expire stale activities (and their QR codes) every day at 12:05 AM
Schedule::command(ExpireActivities::class)->dailyAt('00:05');
