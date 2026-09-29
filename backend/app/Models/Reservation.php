<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'equipment_id',
        'reserved_date',
        'reserved_until',
        'purpose',
        'status',
        'approved_by',
        'remarks',
    ];

    protected $casts = [
        'reserved_date' => 'date',
        'reserved_until' => 'date',
    ];

    // Reservation history must stay readable even if the requester, the
    // equipment, or the approver is later soft-deleted.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }
}
