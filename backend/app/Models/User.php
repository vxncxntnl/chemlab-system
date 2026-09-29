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
}
