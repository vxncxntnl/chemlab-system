<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One physical unit of a serial-tracked equipment item (e.g. one specific
 * microscope): a system tag (unit_code) and its factory serial number.
 * It is "under maintenance" while a maintenance log for it is scheduled or
 * in progress — worked out from the logs, so it never gets out of sync.
 */
class EquipmentUnit extends Model
{
    protected $fillable = [
        'equipment_id',
        'equipment_inventory_id',
        'unit_code',
        'serial_number',
        'notes',
    ];

    public const OPEN_MAINTENANCE = ['scheduled', 'in_progress'];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(EquipmentInventory::class, 'equipment_inventory_id');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    /** The open maintenance log for this unit, if it's under maintenance now. */
    public function openMaintenance(): HasOne
    {
        return $this->hasOne(MaintenanceLog::class)->whereIn('status', self::OPEN_MAINTENANCE)->latestOfMany();
    }

    /** "EQP-00014-03 (SN MC801…)" — how a unit is shown in lists. */
    public function label(): string
    {
        return $this->unit_code . ($this->serial_number ? " (SN {$this->serial_number})" : ' (no serial yet)');
    }

    /** The next free tag for an equipment item, e.g. EQP-00014-21. */
    public static function nextCode(Equipment $equipment): string
    {
        $n = static::where('equipment_id', $equipment->id)->count();
        do {
            $n++;
            $code = sprintf('%s-%02d', $equipment->equipment_code, $n);
        } while (static::where('unit_code', $code)->exists());

        return $code;
    }
}
