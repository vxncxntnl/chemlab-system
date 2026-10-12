<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    protected $fillable = [
        'equipment_id',
        'equipment_unit_id',
        'campus_id',
        'performed_by',
        'type',
        'description',
        'cost',
        'maintenance_date',
        'next_maintenance_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'maintenance_date' => 'date',
        'next_maintenance_date' => 'date',
    ];

    // Maintenance history must stay readable even after the equipment or
    // the staff member who performed the work is later soft-deleted.
    /**
     * Types offered for new logs. "Replacement" isn't one: damaged items are
     * replaced through Damage Reports (the lab's replace policy), so it would
     * record the same event in two places. Older logs may still have it.
     */
    public const TYPES = ['routine', 'repair', 'inspection', 'calibration'];

    /** A new log can't start as "cancelled" — that's only for calling off a planned one (Edit). */
    public const NEW_STATUSES = ['scheduled', 'in_progress', 'completed'];

    /** Only recurring work has a "next maintenance date". */
    public const RECURRING_TYPES = ['routine', 'inspection', 'calibration'];

    /**
     * Shared clean-up for saving a log: a next date only for recurring
     * types, and a blank cost saved as 0 (the column requires a value).
     */
    public static function normalize(array $data): array
    {
        if (!in_array($data['type'] ?? null, self::RECURRING_TYPES, true)) {
            $data['next_maintenance_date'] = null;
        }
        $data['cost'] = $data['cost'] ?? 0;

        return $data;
    }

    /** The exact unit (serial number) being maintained, for serial-tracked items. */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(EquipmentUnit::class, 'equipment_unit_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by')->withTrashed();
    }
}
