<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowingEquipment extends Model
{
    use HasFactory;

    protected $table = 'borrowing_equipment';

    protected $fillable = [
        'borrowing_id',
        'equipment_id',
        'inventory_id',
        'quantity',
        'condition_on_borrow',
        'condition_on_return',
        'damage_report_status',
        'damage_reported_at',
        'damage_report_note',
    ];

    protected $casts = [
        // Sent to the pages with its timezone, so the time shows correctly.
        'damage_reported_at' => 'datetime',
    ];

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }

    // A borrowing line item must still show what equipment was involved even
    // if that equipment is later soft-deleted from the catalog.
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(EquipmentInventory::class, 'inventory_id');
    }
}
