<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Implementing MustVerifyEmail activates verification enforcement that was
 * already fully scaffolded but dormant: the `verified` middleware on
 * /dashboard, the verify-email routes/controllers, and the VerifyEmail.vue
 * prompt page all already existed and worked against this exact contract —
 * they just had nothing to enforce without this interface. This closes the
 * "register with someone else's real email" gap: the verification link
 * goes to the real mailbox, so an attacker who registered with a victim's
 * email can never click it and can never get past the verification prompt.
 * Google-authenticated users are exempt from needing this separately —
 * completing Google OAuth already proves mailbox ownership just as well,
 * so GoogleAuthController marks their email verified directly.
 *
 * SoftDeletes: deleting a user (Admin > Users > Delete) previously cascaded
 * to hard-delete every borrowing they ever made, and separately cascaded to
 * delete every audit_log row where they're the actor — including entries
 * about OTHER things they did as an approver/admin, unrelated to why they
 * were removed. With soft deletes, the row is hidden/deactivated instead of
 * actually removed, so those cascades never fire and history stays intact
 * (readable via ->withTrashed() on the relationships that reference users).
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes;

    /** Marks an email that was released when its account was deleted. */
    public const RELEASED_EMAIL_MARKER = '#deleted-';

    /**
     * A deleted (soft-deleted) account keeps its row for history, but must
     * not keep holding its email: otherwise that email could never be used
     * again — "already exists" on sign-up / admin create / faculty add, and
     * a crash on Google sign-in (which can't see the hidden row, then hits
     * the unique email index). So on delete the email is renamed to
     * "original#deleted-{id}" (still readable in history, never a real
     * address) and the Google link is cleared.
     */
    protected static function booted(): void
    {
        static::deleted(function (User $user) {
            if ($user->isForceDeleting()) {
                return;
            }
            $user->forceFill([
                'email'     => self::releasedEmail($user->email, $user->id),
                'google_id' => null,
            ])->saveQuietly();
        });
    }

    public static function releasedEmail(string $email, int $id): string
    {
        return str_contains($email, self::RELEASED_EMAIL_MARKER)
            ? $email
            : $email . self::RELEASED_EMAIL_MARKER . $id;
    }

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'status',
        'department',
        'course',
        'student_id',
        'employee_id',
        'contact_number',
        'campus_id',
        'google_id',
        'email_verified_at',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Return combined first + last name, falling back to the legacy `name` field.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name || $this->last_name) {
            return trim("{$this->first_name} {$this->last_name}");
        }
        return $this->name ?? '';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }

    public function approvedBorrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class, 'approved_by');
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class, 'reported_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isLabStaff(): bool
    {
        return $this->role === 'lab_staff';
    }

    public function isFaculty(): bool
    {
        return $this->role === 'faculty';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /**
     * Has this user already been through Complete Your Profile? Role-specific
     * since faculty and students fill in different fields there.
     */
    public function hasCompletedProfile(): bool
    {
        if ($this->isFaculty()) {
            return (bool) ($this->campus_id && $this->department);
        }

        return (bool) ($this->campus_id && $this->student_id && $this->course);
    }

    /*
     * Account limits: each campus has at most one active admin and one
     * active lab staff account. "Active" = not deleted and not suspended, so
     * a suspended account can be replaced. Enforced in Admin\UserController
     * and by partial unique indexes on the users table.
     */

    /** The campus's active admin account, other than $exceptId (null if none). */
    public static function activeAdminAt(?int $campusId, ?int $exceptId = null): ?self
    {
        if (!$campusId) {
            return null;
        }

        // (An admin with no campus counts as the main campus's.)
        $main = \App\Support\CampusAccess::mainCampusId();

        return static::where('role', 'admin')
            ->where(fn ($q) => $q->where('campus_id', $campusId)->when($campusId === $main, fn ($q) => $q->orWhereNull('campus_id')))
            ->where('status', '!=', 'suspended')
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->first();
    }

    /** The campus's active lab staff account, other than $exceptId (null if none). */
    public static function activeLabStaffAt(?int $campusId, ?int $exceptId = null): ?self
    {
        if (!$campusId) {
            return null;
        }

        return static::where('role', 'lab_staff')
            ->where('campus_id', $campusId)
            ->where('status', '!=', 'suspended')
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->first();
    }

    /**
     * The Lab Inventory masterfile (chemistry/physics items) belongs to the
     * main laboratory at Carmen — it has no campus of its own — so among lab
     * staff only Carmen's may view or edit it. The admin manages it from the
     * admin side.
     */
    public function managesLabMasterfile(): bool
    {
        return $this->role === 'lab_staff'
            && stripos((string) $this->campus?->campus_name, 'carmen') !== false;
    }
}
