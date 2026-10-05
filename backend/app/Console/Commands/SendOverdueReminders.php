<?php

namespace App\Console\Commands;

use App\Mail\OverdueReminder;
use App\Models\Borrowing;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOverdueReminders extends Command
{
    protected $signature = 'borrowings:send-overdue-reminders';
    protected $description = 'Send email reminders for equipment due tomorrow and overdue borrowings';

    public function handle(): void
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        // 1. Send "due tomorrow" reminders
        $dueTomorrow = Borrowing::with('user')
            ->whereIn('status', ['approved', 'borrowed'])
            ->whereDate('return_date', $tomorrow)
            ->get();

        foreach ($dueTomorrow as $borrowing) {
            if ($borrowing->user && $borrowing->user->email) {
                $this->deliver($borrowing, false, "Reminder sent to {$borrowing->user->email} for {$borrowing->claim_code} (due tomorrow)");
            }
        }

        // 2. Send overdue reminders
        $overdue = Borrowing::with('user')
            ->whereIn('status', ['approved', 'borrowed', 'overdue'])
            ->whereDate('return_date', '<', $today)
            ->get();

        foreach ($overdue as $borrowing) {
            // Mark as overdue if not already
            if ($borrowing->status !== 'overdue') {
                $borrowing->update(['status' => 'overdue', 'overdue_flag' => true]);
            }

            if ($borrowing->user && $borrowing->user->email) {
                $this->deliver($borrowing, true, "Overdue notice sent to {$borrowing->user->email} for {$borrowing->claim_code}");
            }
        }

        $this->info("Done. Reminders: {$dueTomorrow->count()} due-tomorrow, {$overdue->count()} overdue.");
    }

    /**
     * Sent immediately rather than queued: the hosted server runs no queue
     * worker, so queued reminders would sit in the jobs table forever. One
     * failed address is logged without stopping the rest.
     */
    private function deliver(Borrowing $borrowing, bool $isOverdue, string $successMessage): void
    {
        try {
            Mail::to($borrowing->user->email)->send(new OverdueReminder($borrowing, $isOverdue));
            $this->info($successMessage);
        } catch (\Throwable $e) {
            report($e);
            $this->error("Failed to email {$borrowing->user->email} for {$borrowing->claim_code}: {$e->getMessage()}");
        }
    }
}
