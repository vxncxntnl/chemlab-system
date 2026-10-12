<?php

namespace App\Http\Controllers;

use App\Console\Commands\SendOverdueReminders;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * The hosted server (Render free plan) has no cron and sleeps when idle, so
 * Laravel's scheduler never fires the 8:00 AM overdue reminders there. An
 * external scheduler (e.g. cron-job.org) calls this URL daily instead, which
 * also wakes the server.
 */
class CronController extends Controller
{
    public function overdueReminders(string $token)
    {
        $secret = (string) config('services.cron.secret');

        if ($secret === '' || !hash_equals($secret, $token)) {
            abort(404);
        }

        // Retries or duplicate pings on the same day must not email
        // borrowers twice.
        if (!Cache::add('cron:overdue-reminders:' . now()->toDateString(), true, now()->addHours(26))) {
            return response('Overdue reminders were already sent today.');
        }

        // Runs after the response is sent, so the scheduler's short request
        // timeout isn't hit while emails are going out.
        defer(fn () => Artisan::call(SendOverdueReminders::class));
        defer(fn () => app(\App\Services\MaintenanceReminderService::class)->sendDueTodayOncePerDay());

        return response('Overdue reminders started.');
    }
}
