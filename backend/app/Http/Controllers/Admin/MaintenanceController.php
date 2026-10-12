<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MaintenanceLog;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Support\CampusAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MaintenanceController extends Controller
{
    public function index()
    {
        $maintenanceLogs = CampusAccess::maintenance(MaintenanceLog::query(), CampusAccess::adminCampusId(auth()->user()))->with(['equipment', 'performer', 'unit:id,unit_code,serial_number'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Maintenance/Index', [
            'maintenanceLogs' => $maintenanceLogs,
        ]);
    }

    public function create()
    {
        // A–Z, ignoring upper/lower case (names mix "BURETTE" and "Bunsen Burner").
        $equipment = CampusAccess::equipment(Equipment::query(), CampusAccess::adminCampusId(auth()->user()))->select('id', 'name', 'serial_number')->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return Inertia::render('Admin/Maintenance/Create', [
            // Serial-tracked items list their units, so the exact one can be chosen.
            'equipment' => \App\Support\MaintenanceUnits::withUnits($equipment, CampusAccess::adminCampusId(auth()->user())),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'equipment_id' => ['required', Rule::exists('equipment', 'id')->where(fn ($q) => $q->whereIn('id', EquipmentInventory::where('campus_id', CampusAccess::adminCampusId(auth()->user()))->select('equipment_id')))],
            'type' => ['required', \Illuminate\Validation\Rule::in(MaintenanceLog::TYPES)],
            'description' => 'required|string',
            'cost' => 'nullable|numeric|min:0',
            'maintenance_date' => 'required|date',
            'next_maintenance_date' => 'nullable|date|after:maintenance_date',
            'status' => ['required', \Illuminate\Validation\Rule::in(MaintenanceLog::NEW_STATUSES)],
            'notes' => 'nullable|string',
        ]);

        $validated['performed_by'] = auth()->id();
        $validated['equipment_unit_id'] = \App\Support\MaintenanceUnits::validatedUnitId($request, CampusAccess::adminCampusId(auth()->user()));
        $validated['campus_id'] = CampusAccess::adminCampusId(auth()->user());

        $maintenance = MaintenanceLog::create(MaintenanceLog::normalize($validated));
        // Scheduled for today: tell the campus lab staff/admin now.
        app(\App\Services\MaintenanceReminderService::class)->notifyIfToday($maintenance, auth()->user());

        // Update equipment condition if needed
        if ($validated['type'] === 'repair' && $validated['status'] === 'completed') {
            Equipment::where('id', $validated['equipment_id'])
                ->update(['condition' => 'good']);
        }

        AuditLog::log(auth()->id(), 'Created Maintenance Log', 'maintenance_logs', $maintenance->id);

        return redirect()->route('admin.maintenance.index')
            ->with('success', 'Maintenance log created successfully.');
    }

    public function show(MaintenanceLog $maintenance)
    {
        $maintenance->load(['equipment', 'performer', 'unit']);

        return Inertia::render('Admin/Maintenance/Show', [
            'maintenance' => $maintenance,
        ]);
    }

    public function edit(MaintenanceLog $maintenance)
    {
        // A–Z, ignoring upper/lower case (names mix "BURETTE" and "Bunsen Burner").
        $equipment = CampusAccess::equipment(Equipment::query(), CampusAccess::adminCampusId(auth()->user()))->select('id', 'name', 'serial_number')->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return Inertia::render('Admin/Maintenance/Edit', [
            'maintenance' => $maintenance,
            // Serial-tracked items list their units, so the exact one can be chosen.
            'equipment' => \App\Support\MaintenanceUnits::withUnits($equipment, CampusAccess::adminCampusId(auth()->user())),
        ]);
    }

    public function update(Request $request, MaintenanceLog $maintenance)
    {
        $validated = $request->validate([
            'equipment_id' => ['required', Rule::exists('equipment', 'id')->where(fn ($q) => $q->whereIn('id', EquipmentInventory::where('campus_id', CampusAccess::adminCampusId(auth()->user()))->select('equipment_id')))],
            'type' => ['required', \Illuminate\Validation\Rule::in([...MaintenanceLog::TYPES, $maintenance->type])],
            'description' => 'required|string',
            'cost' => 'nullable|numeric|min:0',
            'maintenance_date' => 'required|date',
            'next_maintenance_date' => 'nullable|date|after:maintenance_date',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $validated['equipment_unit_id'] = \App\Support\MaintenanceUnits::validatedUnitId($request, CampusAccess::adminCampusId(auth()->user()));
        $maintenance->update(MaintenanceLog::normalize($validated));
        app(\App\Services\MaintenanceReminderService::class)->notifyIfToday($maintenance->fresh(), auth()->user());

        AuditLog::log(auth()->id(), 'Updated Maintenance Log', 'maintenance_logs', $maintenance->id);

        return redirect()->route('admin.maintenance.index')
            ->with('success', 'Maintenance log updated successfully.');
    }

    public function destroy(MaintenanceLog $maintenance)
    {
        AuditLog::log(auth()->id(), 'Deleted Maintenance Log', 'maintenance_logs', $maintenance->id);

        $maintenance->delete();

        return redirect()->route('admin.maintenance.index')
            ->with('success', 'Maintenance log deleted successfully.');
    }
}
