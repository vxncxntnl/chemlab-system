<?php

namespace App\Services;

use App\Mail\AccountStatusMail;
use App\Mail\BorrowingStatusMail;
use App\Models\Activity;
use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\DamageReport;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;

class NotificationService
{
    // The admin's notification for each QR activity request; the grouped
    // Borrowings list counts the unread ones per activity.
    public const QR_REQUEST_TITLE = 'New QR Activity Request — Waiting for Your Approval';

    public static function send(User $user, string $title, string $message, string $type = 'info', ?string $link = null): void
    {
        // In-system notification
        UserNotification::create([
            'user_id' => $user->id,
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
            'link'    => $link,
        ]);
    }

    public static function sendWithEmail(User $user, string $title, string $message, string $type = 'info', ?string $link = null): void
    {
        self::send($user, $title, $message, $type, $link);

        // Send email
        try {
            Mail::to($user->email)->send(new BorrowingStatusMail($user, $title, $message));
        } catch (\Throwable $e) {
            // The in-system notification is already saved, so the user's
            // action still succeeds — but the failure is logged (it used to
            // be swallowed silently, which hid why hosted email wasn't
            // arriving).
            report($e);
        }
    }

    // ─── Account Notifications ────────────────────────────────────────────────

    public static function accountPendingApproval(User $user): void
    {
        self::sendWithEmail(
            $user,
            'Account Submitted',
            'Your account has been submitted successfully. Please wait for the administrator to review and approve your account.',
            'info'
        );
    }

    public static function facultyRegistered(User $user): void
    {
        // Notify the faculty themselves
        self::sendWithEmail(
            $user,
            'Faculty Account Pending Approval',
            "Welcome, {$user->name}! Your faculty account has been submitted successfully. An administrator needs to verify it before you can log in — you'll get another email once that's done.",
            'info'
        );

        // Notify all admins to verify the new faculty
        self::notifyRole(
            'admin',
            'New Faculty Registration — Please Verify',
            "{$user->name} ({$user->email}) has registered as a faculty member under the {$user->department} department at {$user->campus?->campus_name}. Please verify this account in Users management.",
            'info',
            '/admin/users'
        );
    }

    public static function accountApproved(User $user): void
    {
        self::sendWithEmail(
            $user,
            'Account Approved',
            'Your account has been approved. You can now log in to the Science Laboratory Borrowing System.',
            'success'
        );
    }

    public static function accountSuspended(User $user): void
    {
        self::sendWithEmail(
            $user,
            'Account Suspended',
            'Your account has been suspended. Please contact the laboratory administrator for more information.',
            'error'
        );
    }

    // ─── Borrowing Notifications ──────────────────────────────────────────────

    private static function notifyRole(string $role, string $title, string $message, string $type, string $link): void
    {
        User::where('role', $role)->where('status', 'active')->each(function (User $user) use ($title, $message, $type, $link) {
            self::sendWithEmail($user, $title, $message, $type, $link);
        });
    }

    private static function borrowingLink(Borrowing $borrowing): string
    {
        $user = $borrowing->user;
        $prefix = match ($user->role) {
            'faculty' => '/faculty',
            default   => '/student',
        };
        return "{$prefix}/borrowings/{$borrowing->id}";
    }

    public static function borrowingSubmitted(Borrowing $borrowing, bool $notifyFaculty = true): void
    {
        $user        = $borrowing->user;
        $facultyName = $borrowing->faculty?->name ?? 'your faculty';

        // Notify the student
        self::sendWithEmail(
            $user,
            'Borrow Request Submitted — Waiting for Faculty Approval',
            "Your borrow request #{$borrowing->id} has been submitted successfully. It is now waiting for approval from {$facultyName}.",
            'info',
            self::borrowingLink($borrowing)
        );

        // Notify the faculty — skipped when the request will be auto-approved via
        // an activity QR code, since "requires your approval" would be inaccurate
        // (see borrowingAutoApprovedViaQr for the notification sent in that case).
        if ($notifyFaculty && $borrowing->faculty) {
            self::sendWithEmail(
                $borrowing->faculty,
                'New Borrow Request Awaiting Your Approval',
                "A new borrow request #{$borrowing->id} from {$user->name} requires your approval.",
                'info',
                "/faculty/borrowings/{$borrowing->id}"
            );
        }
    }

    public static function borrowingAutoApprovedViaQr(Borrowing $borrowing, Activity $activity): void
    {
        // The request now waits for the admin, so tell them — in-app only:
        // a whole class scanning the QR shouldn't flood their inbox.
        User::where('role', 'admin')->where('status', 'active')->each(fn (User $admin) => self::send(
            $admin,
            self::QR_REQUEST_TITLE,
            "{$borrowing->user->name} requested equipment for the activity \"{$activity->title}\" by scanning its QR code (request #{$borrowing->id}).",
            'info',
            "/admin/borrowings/{$borrowing->id}"
        ));

        if (!$borrowing->faculty) {
            return;
        }

        $user = $borrowing->user;
        $equipmentNames = $borrowing->borrowingEquipment->pluck('equipment.name')->filter()->implode(', ');

        self::sendWithEmail(
            $borrowing->faculty,
            'Student Borrowed Equipment via Your Activity QR Code',
            "{$user->name} scanned the QR code for your activity \"{$activity->title}\" and borrowed {$equipmentNames} (request #{$borrowing->id}). " .
                "This request has been auto-approved on your behalf — no action is needed from you.",
            'info',
            "/faculty/borrowings/{$borrowing->id}"
        );
    }

    public static function borrowingApprovedByFaculty(Borrowing $borrowing, User $approver): void
    {
        $user = $borrowing->user;

        // Notify the student
        self::sendWithEmail(
            $user,
            'Approved by Faculty — Waiting for Admin Approval',
            "Your borrow request #{$borrowing->id} has been approved by {$approver->name}. It is now waiting for approval from the administrator.",
            'success',
            self::borrowingLink($borrowing)
        );

        // Notify all admins
        self::notifyRole(
            'admin',
            'New Borrow Request Awaiting Admin Approval',
            "Borrow request #{$borrowing->id} from {$user->name} has been approved by faculty ({$approver->name}) and is now waiting for your review.",
            'info',
            "/admin/borrowings/{$borrowing->id}"
        );
    }

    public static function borrowingRejectedByFaculty(Borrowing $borrowing, User $approver): void
    {
        $user = $borrowing->user;
        $message = "Your borrow request #{$borrowing->id} has been rejected by {$approver->name}.";
        $message .= $borrowing->rejection_reason
            ? " Reason: \"{$borrowing->rejection_reason}\""
            : ' Please coordinate with your faculty for more information.';

        self::sendWithEmail(
            $user,
            'Borrow Request Rejected by Faculty',
            $message,
            'error',
            self::borrowingLink($borrowing)
        );
    }

    public static function borrowingApprovedByAdmin(Borrowing $borrowing, User $approver): void
    {
        $user = $borrowing->user;

        // Notify the student
        self::sendWithEmail(
            $user,
            'Borrow Request Fully Approved',
            "Your borrow request #{$borrowing->id} has been approved by {$approver->name}. It is now waiting for equipment release by the laboratory staff.",
            'success',
            self::borrowingLink($borrowing)
        );

        // Notify lab staff to prepare for release
        self::notifyRole(
            'lab_staff',
            'Borrow Request Approved — Ready for Equipment Release',
            "Borrow request #{$borrowing->id} from {$user->name} has been fully approved by admin ({$approver->name}). Please prepare the equipment for release.",
            'success',
            "/lab/borrowings/{$borrowing->id}"
        );
    }

    public static function borrowingRejectedByAdmin(Borrowing $borrowing, User $approver): void
    {
        $user = $borrowing->user;
        $message = "Your borrow request #{$borrowing->id} has been rejected by {$approver->name}.";
        $message .= $borrowing->rejection_reason
            ? " Reason: \"{$borrowing->rejection_reason}\""
            : ' Please contact the laboratory administrator for more information.';

        self::sendWithEmail(
            $user,
            'Borrow Request Rejected by Admin',
            $message,
            'error',
            self::borrowingLink($borrowing)
        );
    }

    public static function borrowingReleased(Borrowing $borrowing, User $approver): void
    {
        $user = $borrowing->user;
        $due = $borrowing->return_date ? date('F j, Y', strtotime($borrowing->return_date)) : 'the agreed date';
        self::sendWithEmail(
            $user,
            'Equipment Released',
            "Your equipment for request #{$borrowing->id} has been released by {$approver->name}. Please return it by {$due}.",
            'success',
            self::borrowingLink($borrowing)
        );
    }

    public static function borrowingReturned(Borrowing $borrowing): void
    {
        $user = $borrowing->user;
        self::sendWithEmail(
            $user,
            'Equipment Returned',
            "Your borrowed equipment for request #{$borrowing->id} has been returned successfully. Thank you!",
            'success',
            self::borrowingLink($borrowing)
        );
    }

    public static function borrowingCancelledByActivity(Borrowing $borrowing, Activity $activity, User $cancelledBy): void
    {
        self::sendWithEmail(
            $borrowing->user,
            'Request Cancelled — Activity Cancelled',
            "Your borrow request #{$borrowing->id} has been cancelled because the activity \"{$activity->title}\" was cancelled by {$cancelledBy->name}.",
            'warning',
            self::borrowingLink($borrowing)
        );
    }

    public static function borrowingCancelledByFaculty(Borrowing $borrowing, User $faculty): void
    {
        self::sendWithEmail(
            $borrowing->user,
            'Request Cancelled by Faculty',
            "Your borrow request #{$borrowing->id} has been cancelled by {$faculty->name}.",
            'warning',
            self::borrowingLink($borrowing)
        );
    }

    public static function borrowingCancelled(Borrowing $borrowing): void
    {
        $user = $borrowing->user;
        self::sendWithEmail(
            $user,
            'Request Cancelled',
            "Your borrow request #{$borrowing->id} has been cancelled.",
            'warning',
            self::borrowingLink($borrowing)
        );
    }

    /**
     * A student tapped "Report Damage" on a borrowed item: alert the lab
     * staff of the campus that item belongs to AND the admins (who cover
     * when no lab staff is around), so someone can check it when the
     * student brings it in. No damage report exists yet — staff record it
     * after verifying.
     */
    public static function possibleDamageReported(Borrowing $borrowing, BorrowingEquipment $item, User $student): void
    {
        $campusId = $item->inventory?->campus_id;
        $labStaff = User::where('role', 'lab_staff')->where('status', 'active')
            ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
            ->get();
        $admins = User::where('role', 'admin')->where('status', 'active')->get();
        $recipients = $labStaff->merge($admins)->unique('id');

        $equipment = $item->equipment?->name ?? 'an item';
        $location = $item->inventory?->location?->location_name;
        $message = "{$student->name} reported that {$equipment} (borrowing #{$borrowing->id}"
            . ($location ? ", {$location}" : '') . ") may be damaged."
            . ($item->damage_report_note ? " Student's note: \"{$item->damage_report_note}\"." : '')
            . ' Please check the item when the student brings it to the laboratory, then record a damage report if confirmed.';

        foreach ($recipients as $recipient) {
            self::sendWithEmail(
                $recipient,
                'Possible Damage Reported by Student',
                $message,
                'warning',
                $recipient->role === 'admin' ? "/admin/borrowings/{$borrowing->id}" : "/lab/borrowings/{$borrowing->id}"
            );
        }
    }

    /**
     * Lab staff checked an item the student reported and found it fine.
     */
    public static function noDamageFound(Borrowing $borrowing, BorrowingEquipment $item, User $checkedBy): void
    {
        $equipment = $item->equipment?->name ?? 'the item';
        self::sendWithEmail(
            $borrowing->user,
            'Equipment Checked — No Damage Found',
            "{$checkedBy->name} checked {$equipment} from your borrow request #{$borrowing->id} and found no damage. No damage report was filed.",
            'success',
            self::borrowingLink($borrowing)
        );
    }

    /**
     * A faculty member created this student's account (or resent the link):
     * email a one-time "Set Your Password" link. The faculty never sees the
     * password. A mail failure is logged, not shown — the student can still
     * use Forgot password.
     */
    public static function studentAccountCreated(User $student, User $faculty): void
    {
        try {
            Mail::to($student->email)->send(new \App\Mail\AccountSetupMail(
                $student,
                $faculty->name,
                \App\Support\AccountSetupLink::for($student)
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function damageReportFiled(DamageReport $report): void
    {
        $borrowing = $report->borrowing;
        $student   = $borrowing?->user;
        $faculty   = $borrowing?->faculty;
        $equipment = $report->equipment;

        $liableLabel = match ($report->liable_party) {
            'student' => 'Student (borrower)',
            'faculty' => 'Faculty (instructor in charge)',
            'shared'  => 'Shared — both student and faculty',
            default   => 'To be determined',
        };

        // Notify faculty — they need to follow up with the student
        if ($faculty) {
            self::sendWithEmail(
                $faculty,
                'Incident Report Filed — Please Contact the Student',
                "An incident report has been filed for {$equipment?->name} borrowed by {$student?->name} " .
                    "(Borrowing #{$borrowing->id}). Liable party: {$liableLabel}. " .
                    "Please contact the student to discuss resolution.",
                'warning',
                "/faculty/borrowings/{$borrowing->id}"
            );
        }

        // Notify student — faculty will reach out
        if ($student) {
            self::sendWithEmail(
                $student,
                'Incident Report Filed for Your Borrowing',
                "An incident report has been filed for {$equipment?->name} you borrowed " .
                    "(Borrowing #{$borrowing->id}). Liable party: {$liableLabel}. " .
                    "Your faculty " . ($faculty?->name ?? 'in charge') . " will be contacting you regarding this matter.",
                'warning',
                "/student/borrowings/{$borrowing->id}"
            );
        }
    }

    /**
     * Lab staff (possibly a student assistant) rejected a damage report —
     * the admins are told so they can reopen it if they disagree.
     */
    public static function damageReportRejectedByLabStaff(DamageReport $report, User $labStaff): void
    {
        $equipment = $report->equipment?->name ?? 'the item';
        $days = DamageReport::REOPEN_DAYS;
        $reason = rtrim((string) $report->rejection_reason, '. ');

        User::where('role', 'admin')->where('status', 'active')->each(fn (User $admin) => self::sendWithEmail(
            $admin,
            'Damage Report Rejected by Lab Staff',
            "Lab staff {$labStaff->name} rejected Damage Report #{$report->id} ({$equipment}, borrowing #{$report->borrowing_id}) — reason: {$reason}. You can reopen it within {$days} days if you disagree.",
            'warning',
            "/admin/damage-reports/{$report->id}"
        ));
    }

    public static function damageReported(Borrowing $borrowing): void
    {
        $user = $borrowing->user;
        self::sendWithEmail(
            $user,
            'Damage Report Filed',
            "A damage report has been filed for your borrowing request #{$borrowing->id}. Please coordinate with the laboratory administrator.",
            'error',
            self::borrowingLink($borrowing)
        );
    }
}
