<?php

namespace App\Http\Controllers;

use App\Models\ActivityEquipmentQr;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class QrScanController extends Controller
{
    /**
     * A student scans an equipment QR code shared by their instructor.
     * Unregistered scanners are routed through Google sign-in (PHINMA accounts only)
     * before landing on a borrow request pre-filled with the scanned item + activity.
     */
    public function show(string $token): RedirectResponse
    {
        $qr = ActivityEquipmentQr::where('token', $token)->firstOrFail();

        $qr->increment('scan_count');
        $qr->update(['last_scanned_at' => now()]);

        if ($qr->activity->isExpired() || $qr->activity->status === 'cancelled') {
            $message = static::closedMessage($qr);

            return Auth::check()
                ? redirect()->route('dashboard')->with('error', $message)
                : redirect('/')->with('error', $message);
        }

        session(['qr_token' => $token]);

        if (!Auth::check()) {
            AuditLog::log(
                $qr->activity->instructor_id,
                'QR Code Scanned (guest)',
                'activity_equipment_qr',
                $qr->id,
                null,
                ['activity_id' => $qr->activity_id, 'equipment_inventory_id' => $qr->equipment_inventory_id]
            );

            return redirect()->route('auth.google');
        }

        return static::redirectAfterScan($qr, Auth::user());
    }

    /**
     * Continue the QR redirect chain once a user is authenticated and has a complete profile.
     * Called from GoogleAuthController and CompleteProfileController so the token survives
     * the OAuth + profile-completion redirect chain via the session.
     */
    /**
     * A cancelled activity's QR codes stop working just like an expired one's.
     */
    private static function closedMessage(ActivityEquipmentQr $qr): string
    {
        return $qr->activity->status === 'cancelled'
            ? 'This activity was cancelled by your instructor, so its QR code no longer works.'
            : 'This activity has expired — please ask your instructor for a new QR code.';
    }

    public static function redirectAfterScan(ActivityEquipmentQr $qr, User $user): RedirectResponse
    {
        if ($qr->activity->isExpired() || $qr->activity->status === 'cancelled') {
            session()->forget('qr_token');

            return redirect()->route('dashboard')
                ->with('error', static::closedMessage($qr));
        }

        if (!$user->campus_id || !$user->student_id || !$user->course) {
            return redirect()->route('profile.complete');
        }

        session()->forget('qr_token');

        AuditLog::log(
            $user->id,
            'Borrow via QR Code',
            'activity_equipment_qr',
            $qr->id,
            null,
            ['activity_id' => $qr->activity_id, 'equipment_inventory_id' => $qr->equipment_inventory_id]
        );

        if ($user->role !== 'student') {
            return redirect()->route('dashboard');
        }

        // A QR code is meant for the instructor's own class, shared in that
        // class's group chat. If it gets forwarded outside that circle, a
        // student on a different campus than the instructor is the clearest
        // sign the scan didn't come from the intended group — so skip the
        // roster enrollment and auto-approval shortcut, but still let them
        // land on the borrow form and submit a normal request for review.
        $sameCampus = !$qr->activity->instructor->campus_id
            || $qr->activity->instructor->campus_id === $user->campus_id;

        if (!$sameCampus) {
            AuditLog::log(
                $user->id,
                'QR Scanned Outside Instructor Campus',
                'activity_equipment_qr',
                $qr->id,
                null,
                ['activity_id' => $qr->activity_id, 'equipment_inventory_id' => $qr->equipment_inventory_id]
            );

            return redirect()->route('student.borrowings.create', [
                'inventory_id' => $qr->equipment_inventory_id,
            ]);
        }

        // Scanning a QR is the activity-linked equivalent of being added to the
        // class roster — enroll the student so the activity appears in their
        // borrow form and faculty can track who borrowed what for it.
        $qr->activity->students()->syncWithoutDetaching([$user->id]);

        return redirect()->route('student.borrowings.create', [
            'inventory_id' => $qr->equipment_inventory_id,
            'activity_id'  => $qr->activity_id,
        ]);
    }
}
