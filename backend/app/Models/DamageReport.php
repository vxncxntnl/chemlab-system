<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'borrowing_id',
        'equipment_id',
        'quantity',
        'equipment_inventory_id',
        'reported_by',
        'description',
        'photo_path',
        'liable_party',
        'liable_reason',
        'status',
        'date_reported',
        'resolved_date',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'status_before_rejection',
        'rejection_restocked_qty',
    ];

    /** An admin can reopen a rejected report for this many days. */
    public const REOPEN_DAYS = 7;

    /** Quick-pick reasons for rejecting a report ("Other" needs details). */
    public const REJECT_REASONS = [
        'No damage found after inspection',
        'Dirty or stained only — cleaned, works normally',
        'Filed by mistake (wrong item or borrowing)',
        'Duplicate report',
        'Damage existed before this borrowing',
        'Other',
    ];

    /**
     * Validates the reject form and returns the reason to store, e.g.
     * "No damage found after inspection — checked twice, no crack".
     */
    public static function rejectionReasonFrom(\Illuminate\Http\Request $request): string
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', \Illuminate\Validation\Rule::in(self::REJECT_REASONS)],
            'details' => 'nullable|string|max:500|required_if:reason,Other',
        ], [
            'details.required_if' => 'Please describe the reason.',
        ]);

        $details = trim((string) ($validated['details'] ?? ''));
        if ($validated['reason'] === 'Other') {
            return $details;
        }

        return $details !== '' ? "{$validated['reason']} — {$details}" : $validated['reason'];
    }

    protected function casts(): array
    {
        return [
            'date_reported' => 'datetime',
            'resolved_date' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by')->withTrashed();
    }

    public function canBeRejected(): bool
    {
        return in_array($this->status, ['pending', 'assessed'], true);
    }

    public function canBeReopened(): bool
    {
        return $this->status === 'rejected'
            && $this->rejected_at
            && $this->rejected_at->gt(now()->subDays(self::REOPEN_DAYS));
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }

    // Damage reports are exactly the kind of accountability record that
    // must stay readable even if the equipment or the reporting staff
    // member is later soft-deleted.
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(EquipmentInventory::class, 'equipment_inventory_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by')->withTrashed();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }
}
