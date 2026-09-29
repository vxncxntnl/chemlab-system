<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ActivityEquipmentQr extends Model
{
    use HasFactory;

    protected $table = 'activity_equipment_qr';

    protected $appends = ['scan_url'];

    protected $fillable = [
        'activity_id',
        'equipment_inventory_id',
        'token',
        'last_scanned_at',
        'scan_count',
    ];

    protected function casts(): array
    {
        return [
            'last_scanned_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(EquipmentInventory::class, 'equipment_inventory_id');
    }

    public static function findOrCreateFor(int $activityId, int $inventoryId): self
    {
        return static::firstOrCreate(
            ['activity_id' => $activityId, 'equipment_inventory_id' => $inventoryId],
            ['token' => Str::random(40)]
        );
    }

    public function getScanUrlAttribute(): string
    {
        return url("/qr/{$this->token}");
    }
}
