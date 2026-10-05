<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BorrowingStatusMail;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::all()->groupBy('group');

        $mailer = config('mail.default');

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            // Non-secret delivery settings only (never the password), so an
            // admin can see which mail server the hosted app is really using.
            'mail' => [
                'mailer' => $mailer,
                'host' => config("mail.mailers.{$mailer}.host"),
                'port' => config("mail.mailers.{$mailer}.port"),
                'from' => config('mail.from.address'),
            ],
        ]);
    }

    /**
     * Sends a sample notification email to the signed-in admin and reports
     * the real outcome — the only way to verify delivery from the hosted
     * server, which has no shell access.
     */
    public function sendTestEmail()
    {
        $admin = auth()->user();

        try {
            Mail::to($admin->email)->send(new BorrowingStatusMail(
                $admin,
                'Test Email — Science Laboratory Borrowing System',
                'This is a test email sent from System Settings. If you received it, borrowing email notifications are working on this server.'
            ));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', "Test email to {$admin->email} failed: " . Str::limit($e->getMessage(), 300));
        }

        return back()->with('success', "Test email sent to {$admin->email}. Check that inbox (and the Spam folder).");
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($validated['settings'] as $setting) {
            SystemSetting::where('key', $setting['key'])
                ->update(['value' => $setting['value']]);
        }

        AuditLog::log(auth()->id(), 'Updated System Settings', 'system_settings', null, null, [
            'keys' => collect($validated['settings'])->pluck('key')->all(),
        ]);

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}
