<?php

namespace App\Support;

use App\Models\Equipment;
use App\Models\EquipmentUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Maintenance of serial-tracked equipment names the exact unit (serial
 * number), so that unit shows "Under Maintenance" on the equipment's page.
 * Shared by the admin and lab staff maintenance forms.
 */
class MaintenanceUnits
{
    /**
     * Adds each item's units at this campus to the equipment picker options
     * (empty for items counted by quantity only).
     */
    public static function withUnits(Collection $options, ?int $campusId): Collection
    {
        $units = EquipmentUnit::whereIn('equipment_id', $options->pluck('id'))
            ->whereHas('inventory', fn ($q) => $q->where('campus_id', (int) $campusId))
            ->with('openMaintenance')
            ->orderBy('unit_code')
            ->get()
            ->groupBy('equipment_id');

        return $options->map(fn ($item) => (is_array($item) ? $item : $item->toArray()) + [
            'units' => ($units[is_array($item) ? $item['id'] : $item->id] ?? collect())->map(fn (EquipmentUnit $u) => [
                'id' => $u->id,
                'label' => $u->label(),
                'under_maintenance' => (bool) $u->openMaintenance,
            ])->values(),
        ])->values();
    }

    /**
     * The unit for a maintenance request: required (and must be one of this
     * campus's units of that item) when the item tracks serial numbers;
     * null otherwise.
     */
    public static function validatedUnitId(Request $request, ?int $campusId): ?int
    {
        $equipment = Equipment::find($request->input('equipment_id'));
        if (!$equipment?->tracks_units) {
            return null;
        }

        $unitId = (int) $request->input('equipment_unit_id');
        $valid = $unitId && EquipmentUnit::whereKey($unitId)
            ->where('equipment_id', $equipment->id)
            ->whereHas('inventory', fn ($q) => $q->where('campus_id', (int) $campusId))
            ->exists();

        if (!$valid) {
            throw ValidationException::withMessages([
                'equipment_unit_id' => 'Choose which unit (serial number) is being maintained.',
            ]);
        }

        return $unitId;
    }
}
