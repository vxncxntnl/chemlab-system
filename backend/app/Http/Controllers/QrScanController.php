<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityEquipmentQr;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class QrScanController extends Controller
{
    /**
     * A student scans the QR code their instructor shared for a lab
     * activity. Each activity has ONE QR code (activities.qr_token) that
     * leads to a borrow request pre-filled with all of the activity's
     * selected equipment. Older per-item QR codes (activity_equipment_qr)
     * still work and pre-fill just that item.
     *
     * The QR holds only a random token — a reference — never the equipment
     * list, so scanning can't be used to change what an activity contains.
     * Guests sign in first; the token survives sign-in via the session.
     */
    public function show(string $token): RedirectResponse
    {
        [$activity, $itemQr] = static::resolve($token) ?? abort(404);

        if ($itemQr) {
            $itemQr->increment('scan_count');
            $itemQr->update(['last_scanned_at' => now()]);
        } else {
            $activity->increment('qr_scan_count');
        }

        // Closed activity, outside the activity's time, or (Live mode) an
        // old picture of the on-screen code: the scan stops here.
        $message = !$activity->isOpenForRequests()
            ? $activity->closedMessage()
            : ($activity->qrWindowMessage() ?? ($activity->qr_live && !$activity->acceptsLiveCode(request()->query('c'))
                ? 'This QR code has already changed. Scan the QR code shown on your instructor\'s screen right now.'
                : null));

        if ($message) {
            return Auth::check()
                ? redirect()->route('dashboard')->with('error', $message)
                : redirect()->route('login')->with('status', $message);
        }

        session(['qr_token' => $token]);

        if (!Auth::check()) {
            AuditLog::log($activity->instructor_id, 'QR Code Scanned (guest)', 'activities', $activity->id, null, [
                'activity_id' => $activity->id,
                'equipment_inventory_id' => $itemQr?->equipment_inventory_id,
            ]);

            // Email/password or Google — both continue to the activity after.
            return redirect()->route('login')
                ->with('status', "Sign in to borrow equipment for \"{$activity->title}\".");
        }

        return static::redirectAfterScan($token, Auth::user());
    }

    /**
     * A token is either an activity's single QR token or an older per-item
     * QR token. Returns [Activity, ?ActivityEquipmentQr] or null.
     */
    public static function resolve(string $token): ?array
    {
        if ($activity = Activity::where('qr_token', $token)->first()) {
            return [$activity, null];
        }
        if ($itemQr = ActivityEquipmentQr::with('activity')->where('token', $token)->first()) {
            return $itemQr->activity ? [$itemQr->activity, $itemQr] : null;
        }

        return null;
    }

    /**
     * After sign-in (password or Google) or completing the profile: if the
     * user arrived from a QR scan, continue to the activity's borrow form.
     */
    public static function continuePendingScan(User $user): ?RedirectResponse
    {
        $token = session('qr_token');
        if (!$token) {
            return null;
        }
        if (!static::resolve($token)) {
            session()->forget('qr_token');

            return null;
        }

        return static::redirectAfterScan($token, $user);
    }

    public static function redirectAfterScan(string $token, User $user): RedirectResponse
    {
        $resolved = static::resolve($token);
        if (!$resolved) {
            session()->forget('qr_token');

            return redirect()->route('dashboard')->with('error', 'That QR code is no longer valid.');
        }
        [$activity, $itemQr] = $resolved;

        if (!$activity->isOpenForRequests()) {
            session()->forget('qr_token');

            return redirect()->route('dashboard')->with('error', $activity->closedMessage());
        }

        if (!$user->campus_id || !$user->student_id || !$user->course) {
            return redirect()->route('profile.complete');
        }

        session()->forget('qr_token');

        AuditLog::log($user->id, 'Borrow via QR Code', 'activities', $activity->id, null, [
            'activity_id' => $activity->id,
            'equipment_inventory_id' => $itemQr?->equipment_inventory_id,
        ]);

        if ($user->role !== 'student') {
            return redirect()->route('dashboard');
        }

        // A QR shared outside the class (e.g. a Criminology student scanning
        // a Nursing activity's code) can't be used to borrow at all.
        if (!$activity->allowsCourse($user->course)) {
            AuditLog::log($user->id, 'QR Scan Blocked — Different Course', 'activities', $activity->id, null, [
                'activity_id' => $activity->id,
                'activity_department' => $activity->department,
                'student_course' => $user->course,
            ]);

            return redirect()->route('dashboard')->with('error',
                "This QR code is only for {$activity->department} students (activity: {$activity->title}). Your course is {$user->course}, so you can't borrow through it."
            );
        }

        // A student of another campus is the clearest sign the QR left its
        // class: rejected outright.
        $sameCampus = !$activity->instructor?->campus_id
            || (int) $activity->instructor->campus_id === (int) $user->campus_id;

        if (!$sameCampus) {
            AuditLog::log($user->id, 'QR Scan Blocked — Different Campus', 'activities', $activity->id, null, [
                'activity_id' => $activity->id,
            ]);

            return redirect()->route('dashboard')->with('error',
                'This QR code is for an activity at another campus, so you can\'t borrow through it.'
            );
        }

        // The school's enrollment records aren't available, so the instructor
        // decides who is in the class: scanning only asks to join. Students
        // the instructor approved before for the same class are let in.
        $entry = $activity->students()->where('activity_students.student_id', $user->id)->first();
        $status = $entry?->pivot->status;

        if (!$entry) {
            $status = $activity->wasApprovedBeforeForSameClass($user->id) ? 'approved' : 'pending';
            $activity->students()->attach($user->id, [
                'status' => $status,
                'approved_at' => $status === 'approved' ? now() : null,
            ]);

            if ($status === 'pending') {
                NotificationService::joinRequestReceived($activity, $user);
            }
        }

        if ($status === 'pending') {
            return redirect()->route('dashboard')->with('info',
                "You asked to join \"{$activity->title}\". Please wait for your instructor to approve you — you'll get a notification, then you can borrow."
            );
        }
        if ($status === 'rejected') {
            return redirect()->route('dashboard')->with('error',
                "Your instructor didn't add you to \"{$activity->title}\". If you are in this class, please talk to your instructor."
            );
        }

        // Activity QR: the form is pre-filled with all of the activity's
        // equipment. Old per-item QR: just that item.
        return redirect()->route('student.borrowings.create', array_filter([
            'inventory_id' => $itemQr?->equipment_inventory_id,
            'activity_id'  => $activity->id,
        ]));
    }
}
