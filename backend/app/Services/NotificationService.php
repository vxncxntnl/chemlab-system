<?php

namespace App\Services;

use App\Mail\AccountStatusMail;
use App\Mail\BorrowingStatusMail;
use App\Models\Activity;
use App\Models\Borrowing;
use App\Models\DamageReport;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;

class NotificationService
{
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
                "An incident report has been filed for {$equipment?->name} returned by {$student?->name} " .
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
