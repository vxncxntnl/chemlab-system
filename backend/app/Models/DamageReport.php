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
        'liable_party',
        'liable_reason',
        'status',
        'date_reported',
        'resolved_date',
    ];

    protected function casts(): array
    {
        return [
            'date_reported' => 'datetime',
            'resolved_date' => 'datetime',
        ];
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
