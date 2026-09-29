<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabInventoryTransfer extends Model
{
    use HasFactory;

    protected $table = 'lab_inventory_transfers';

    protected $fillable = [
        'inventory_id',
        'campus',
        'quantity',
        'transfer_date',
        'received_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'transfer_date' => 'date',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(LabInventory::class, 'inventory_id');
    }

    public function scopeIligan($query)
    {
        return $query->where('campus', 'iligan');
    }

    public function scopeButuan($query)
    {
        return $query->where('campus', 'butuan');
    }
}
