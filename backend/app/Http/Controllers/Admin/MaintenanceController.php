<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MaintenanceLog;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceController extends Controller
{
    public function index()
    {
        $maintenanceLogs = MaintenanceLog::with(['equipment', 'performer'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Maintenance/Index', [
            'maintenanceLogs' => $maintenanceLogs,
        ]);
    }

    public function create()
    {
        $equipment = Equipment::select('id', 'name', 'serial_number')->get();

        return Inertia::render('Admin/Maintenance/Create', [
            'equipment' => $equipment,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'equipment_id' => 'required|exists:equipment,id',
            'type' => 'required|in:routine,repair,inspection,calibration,replacement',
            'description' => 'required|string',
            'cost' => 'nullable|numeric|min:0',
            'maintenance_date' => 'required|date',
            'next_maintenance_date' => 'nullable|date|after:maintenance_date',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $validated['performed_by'] = auth()->id();

        $maintenance = MaintenanceLog::create($validated);

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
        $maintenance->load(['equipment', 'performer']);

        return Inertia::render('Admin/Maintenance/Show', [
            'maintenance' => $maintenance,
        ]);
    }

    public function edit(MaintenanceLog $maintenance)
    {
        $equipment = Equipment::select('id', 'name', 'serial_number')->get();

        return Inertia::render('Admin/Maintenance/Edit', [
            'maintenance' => $maintenance,
            'equipment' => $equipment,
        ]);
    }

    public function update(Request $request, MaintenanceLog $maintenance)
    {
        $validated = $request->validate([
            'equipment_id' => 'required|exists:equipment,id',
            'type' => 'required|in:routine,repair,inspection,calibration,replacement',
            'description' => 'required|string',
            'cost' => 'nullable|numeric|min:0',
            'maintenance_date' => 'required|date',
            'next_maintenance_date' => 'nullable|date|after:maintenance_date',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $maintenance->update($validated);

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
