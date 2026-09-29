<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabInventoryLog extends Model
{
    use HasFactory;

    protected $table = 'lab_inventory_logs';

    protected $fillable = [
        'inventory_id',
        'user_id',
        'action',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(LabInventory::class, 'inventory_id');
    }

    // Lab inventory log history must stay attributable even if the staff
    // member is later soft-deleted.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
