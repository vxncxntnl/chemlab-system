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
