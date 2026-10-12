<?php

namespace App\Services;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\CampusAccess;
use Illuminate\Support\Facades\Cache;

/**
 * In-app reminders for maintenance happening today, to the campus's lab
 * staff and admin:
 *  - a log scheduled for today: right when it's saved, and
 *  - on the day itself for one scheduled earlier, plus recurring work whose
 *    "next maintenance date" is today — sent by the daily check (the first
 *    admin/lab staff dashboard visit of the day, or the daily cron link).
 * Each person gets each reminder once per log per day.
 */
class MaintenanceReminderService
{
    public const TITLE_TODAY = 'Maintenance Scheduled Today';
    public const TITLE_DUE = 'Maintenance Due Today';

    /** Today's date at the school (Philippine time), e.g. "2026-10-13". */
    private static function today(): string
    {
        return now(config('app.local_timezone'))->toDateString();
    }

    /** Right after a log is saved: remind now if it's scheduled for today. */
    public function notifyIfToday(MaintenanceLog $log, ?User $savedBy = null): void
    {
        if ($log->status === 'scheduled' && $log->maintenance_date?->toDateString() === self::today()) {
            $this->send($log, self::TITLE_TODAY, $savedBy);
        }
    }

    /** The daily check — runs at most once a day. */
    public function sendDueTodayOncePerDay(): void
    {
        if (Cache::add('maintenance-reminders:' . self::today(), true, now()->addHours(26))) {
            $this->sendDueToday();
        }
    }

    public function sendDueToday(): void
    {
        MaintenanceLog::with(['equipment:id,name', 'unit:id,unit_code,serial_number'])
            ->where('status', 'scheduled')
            ->whereDate('maintenance_date', self::today())
            ->each(fn (MaintenanceLog $log) => $this->send($log, self::TITLE_TODAY));

        MaintenanceLog::with(['equipment:id,name', 'unit:id,unit_code,serial_number'])
            ->whereIn('type', MaintenanceLog::RECURRING_TYPES)
            ->whereIn('status', ['completed', 'scheduled', 'in_progress'])
            ->whereDate('next_maintenance_date', self::today())
            ->each(fn (MaintenanceLog $log) => $this->send($log, self::TITLE_DUE));
    }

    private function send(MaintenanceLog $log, string $title, ?User $except = null): void
    {
        $log->loadMissing(['equipment:id,name', 'unit:id,unit_code,serial_number']);
        $item = ($log->equipment?->name ?? 'Equipment') . ($log->unit ? " ({$log->unit->unit_code})" : '');
        $type = ucfirst(str_replace('_', ' ', $log->type));
        $message = $title === self::TITLE_DUE
            ? "{$type} for {$item} is due again today (next maintenance date). Schedule it if it hasn't been done."
            : "{$type} for {$item} is scheduled for today. {$log->description}";

        $campusId = $log->campus_id;
        $recipients = User::where('role', 'lab_staff')->where('status', 'active')->where('campus_id', $campusId)->get()
            ->merge(CampusAccess::adminsFor($campusId))
            ->unique('id')
            ->reject(fn (User $u) => $except && $u->id === $except->id);

        foreach ($recipients as $user) {
            $link = ($user->role === 'admin' ? '/admin' : '/lab') . "/maintenance/{$log->id}";
            $alreadySent = UserNotification::where('user_id', $user->id)
                ->where('title', $title)->where('link', $link)
                // Sent since the start of today (Philippine time).
                ->where('created_at', '>=', now(config('app.local_timezone'))->startOfDay()->setTimezone(config('app.timezone')))
                ->exists();
            if (!$alreadySent) {
                NotificationService::send($user, $title, $message, 'warning', $link);
            }
        }
    }
}
