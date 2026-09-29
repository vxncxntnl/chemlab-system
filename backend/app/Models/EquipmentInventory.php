<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentInventory extends Model
{
    use HasFactory;

    protected $table = 'equipment_inventory';

    protected $fillable = [
        'equipment_id',
        'campus_id',
        'location_id',
        'total_quantity',
        'available_quantity',
        'reorder_level',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function borrowingEquipment(): HasMany
    {
        return $this->hasMany(BorrowingEquipment::class, 'inventory_id');
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class, 'equipment_inventory_id')->latest();
    }

    public function isLowStock(): bool
    {
        return $this->available_quantity <= $this->reorder_level;
    }
}
