<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    protected $fillable = [
        'equipment_inventory_id',
        'type',
        'quantity',
        'reason',
        'performed_by',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(EquipmentInventory::class, 'equipment_inventory_id');
    }

    // Stock-adjustment history must stay attributable even if the staff
    // member who made the adjustment is later soft-deleted.
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by')->withTrashed();
    }
}
