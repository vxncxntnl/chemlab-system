<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Soft-deleted rather than hard-deleted: a borrowing record is core
 * transaction history (who borrowed what, who approved it, whether it was
 * damaged) and must remain part of the audit trail even if an admin removes
 * it from active views.
 */
class Borrowing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'faculty_id',
        'activity_id',
        'approved_by',
        'faculty_approved_by',
        'faculty_approved_at',
        'lab_approved_by',
        'lab_approved_at',
        'admin_approved_by',
        'admin_approved_at',
        'borrow_date',
        'return_date',
        'actual_return_date',
        'purpose',
        'subject',
        'status',
        'overdue_flag',
        'claim_code',
        'remarks',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'borrow_date' => 'date',
            'return_date' => 'date',
            'actual_return_date' => 'date',
            'overdue_flag' => 'boolean',
            'faculty_approved_at' => 'datetime',
            'lab_approved_at' => 'datetime',
            'admin_approved_at' => 'datetime',
        ];
    }

    // withTrashed() on all three: a borrowing record is exactly the kind of
    // history that must stay readable even if the student, approver, or
    // faculty involved is later soft-deleted from the system.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'faculty_id')->withTrashed();
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function borrowingEquipment(): HasMany
    {
        return $this->hasMany(BorrowingEquipment::class);
    }

    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'borrowing_equipment')
            ->withPivot('quantity', 'condition_on_borrow', 'condition_on_return', 'inventory_id')
            ->withTimestamps();
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending_faculty', 'pending_admin']);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending_admin', 'approved', 'borrowed']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
            ->orWhere(function ($q) {
                $q->whereIn('status', ['approved', 'borrowed'])
                    ->where('return_date', '<', Carbon::today());
            });
    }
}
