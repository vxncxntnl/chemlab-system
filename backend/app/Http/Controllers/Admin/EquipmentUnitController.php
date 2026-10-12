<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\EquipmentUnit;
use App\Support\CampusAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Serial numbers of each unit of a serial-tracked equipment item (edited on
 * the equipment's View page). Units belong to the admin's own campus stock.
 */
class EquipmentUnitController extends Controller
{
    private function rules(Equipment $equipment, ?EquipmentUnit $unit = null): array
    {
        return [
            // The same serial can't be on two units of one item.
            'serial_number' => ['nullable', 'string', 'max:100',
                Rule::unique('equipment_units', 'serial_number')->where('equipment_id', $equipment->id)->ignore($unit?->id)],
            'notes' => 'nullable|string|max:255',
        ];
    }

    /** Add a unit (e.g. a newly bought microscope) at this campus. */
    public function store(Request $request, Equipment $equipment)
    {
        abort_unless($equipment->tracks_units, 404);
        $validated = $request->validate($this->rules($equipment));

        $inventory = EquipmentInventory::where('equipment_id', $equipment->id)
            ->where('campus_id', CampusAccess::adminCampusId(auth()->user()))
            ->firstOrFail();

        $unit = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'equipment_inventory_id' => $inventory->id,
            'unit_code' => EquipmentUnit::nextCode($equipment),
            'serial_number' => $validated['serial_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLog::log(auth()->id(), 'Added Equipment Unit', 'equipment_units', $unit->id, null, ['unit_code' => $unit->unit_code, 'serial_number' => $unit->serial_number]);

        return back()->with('success', "Unit {$unit->unit_code} added.");
    }

    /** Edit a unit's serial number / notes. */
    public function update(Request $request, EquipmentUnit $unit)
    {
        $validated = $request->validate($this->rules($unit->equipment, $unit));
        $old = $unit->only(['serial_number', 'notes']);

        $unit->update([
            'serial_number' => $validated['serial_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLog::log(auth()->id(), 'Updated Equipment Unit', 'equipment_units', $unit->id, $old, $unit->only(['serial_number', 'notes']));

        return back()->with('success', "Unit {$unit->unit_code} updated.");
    }

    /** Remove a unit (e.g. disposed of). Not while it's under maintenance. */
    public function destroy(EquipmentUnit $unit)
    {
        if ($unit->openMaintenance()->exists()) {
            return back()->with('error', "Unit {$unit->unit_code} is under maintenance. Finish or cancel the maintenance first.");
        }

        AuditLog::log(auth()->id(), 'Removed Equipment Unit', 'equipment_units', $unit->id, $unit->only(['unit_code', 'serial_number']));
        $unit->delete();

        return back()->with('success', "Unit {$unit->unit_code} removed.");
    }

    /**
     * When an item is switched to "track serial numbers", give it one unit
     * per item in stock (at every campus stocking it). Existing units stay.
     */
    public static function createMissingUnits(Equipment $equipment): void
    {
        if (!$equipment->tracks_units || $equipment->units()->exists()) {
            return;
        }

        $first = true;
        foreach ($equipment->inventory()->orderBy('id')->get() as $inventory) {
            for ($i = 0; $i < (int) $inventory->total_quantity; $i++) {
                EquipmentUnit::create([
                    'equipment_id' => $equipment->id,
                    'equipment_inventory_id' => $inventory->id,
                    'unit_code' => EquipmentUnit::nextCode($equipment),
                    'serial_number' => $first ? ($equipment->serial_number ?: null) : null,
                ]);
                $first = false;
            }
        }
    }
}
