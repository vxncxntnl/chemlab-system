<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabInventory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lab_inventory';

    protected $fillable = [
        'lab',
        'category',
        'item_no',
        'description',
        'size',
        'quantity',
        'uom',
        'brand',
        'storage_location',
        'condition_remarks',
        'qty_sent_iligan',
        'qty_sent_butuan',
        'current_campus_location',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'qty_sent_iligan' => 'integer',
            'qty_sent_butuan' => 'integer',
        ];
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(LabInventoryTransfer::class, 'inventory_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LabInventoryLog::class, 'inventory_id');
    }

    public function scopeChemistry($query)
    {
        return $query->where('lab', 'chemistry');
    }

    public function scopePhysics($query)
    {
        return $query->where('lab', 'physics');
    }

    public function scopeGeneral($query)
    {
        return $query->where('category', 'general');
    }

    public function scopePasco($query)
    {
        return $query->where('category', 'pasco');
    }

    public function scopeGoodCondition($query)
    {
        return $query->whereLike('condition_remarks', '%Good%')
            ->orWhereLike('condition_remarks', '%Working%');
    }

    public function scopeDefective($query)
    {
        return $query->whereLike('condition_remarks', '%Broken%')
            ->orWhereLike('condition_remarks', '%Damage%')
            ->orWhereLike('condition_remarks', '%Not Working%');
    }

    public function scopeNeedsRepair($query)
    {
        return $query->whereLike('condition_remarks', '%repair%');
    }

    public function getConditionStatusAttribute(): string
    {
        $remarks = strtolower($this->condition_remarks ?? '');

        if (str_contains($remarks, 'disposal')) {
            return 'for_disposal';
        }
        if (str_contains($remarks, 'not working') || str_contains($remarks, 'broken') || str_contains($remarks, 'damage')) {
            return 'defective';
        }
        if (str_contains($remarks, 'repair')) {
            return 'needs_repair';
        }
        return 'good';
    }

    public function getHasTransfersAttribute(): bool
    {
        return $this->qty_sent_iligan > 0 || $this->qty_sent_butuan > 0;
    }

    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->qty_sent_iligan - $this->qty_sent_butuan;
    }
}
