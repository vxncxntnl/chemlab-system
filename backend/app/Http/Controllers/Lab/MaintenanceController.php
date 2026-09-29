<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\MaintenanceLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MaintenanceController extends Controller
{
    public function index()
    {
        $campusId = auth()->user()->campus_id;

        // Only show maintenance logs for equipment at this lab staff's campus
        $logs = MaintenanceLog::with(['equipment', 'performer'])
            ->whereHas('equipment.inventory', function ($q) use ($campusId) {
                $q->where('campus_id', $campusId);
            })
            ->latest()
            ->paginate(15);

        return Inertia::render('Lab/Maintenance/Index', [
            'logs' => $logs,
        ]);
    }

    public function create()
    {
        $campusId = auth()->user()->campus_id;

        // Only show equipment available at this campus
        $equipment = EquipmentInventory::with('equipment')
            ->where('campus_id', $campusId)
            ->get()
            ->map(fn($inv) => [
                'id'   => $inv->equipment->id,
                'name' => $inv->equipment->name . ' (' . $inv->location?->location_name . ')',
            ])
            ->unique('id')
            ->values();

        return Inertia::render('Lab/Maintenance/Create', [
            'equipment' => $equipment,
        ]);
    }

    public function store(Request $request)
    {
        $campusId = auth()->user()->campus_id;

        $validated = $request->validate([
            // create()'s dropdown only ever offers equipment stocked at this
            // lab staff's own campus — enforce that here too, or a crafted
            // request could log maintenance against another campus's item.
            'equipment_id'          => [
                'required',
                Rule::exists('equipment', 'id')->where(
                    fn ($query) => $query->whereIn('id', EquipmentInventory::where('campus_id', $campusId)->pluck('equipment_id'))
                ),
            ],
            'type'                  => 'required|in:routine,repair,inspection,calibration,replacement',
            'description'           => 'required|string',
            'cost'                  => 'nullable|numeric|min:0',
            'maintenance_date'      => 'required|date',
            'next_maintenance_date' => 'nullable|date|after:maintenance_date',
            'status'                => 'required|in:scheduled,in_progress,completed,cancelled',
            'notes'                 => 'nullable|string',
        ]);

        $validated['performed_by'] = auth()->id();
        $validated['cost'] = $validated['cost'] ?? 0;

        MaintenanceLog::create($validated);

        // If it's a completed repair, restore equipment condition to good
        if ($validated['type'] === 'repair' && $validated['status'] === 'completed') {
            Equipment::where('id', $validated['equipment_id'])
                ->update(['condition' => 'good']);
        }

        return redirect()->route('lab.maintenance.index')
            ->with('success', 'Maintenance log recorded successfully.');
    }

    public function show(MaintenanceLog $maintenance)
    {
        $campusId = auth()->user()->campus_id;

        // Ensure this maintenance record belongs to equipment at the lab staff's campus
        $inCampus = $maintenance->equipment->inventory()
            ->where('campus_id', $campusId)
            ->exists();

        if (!$inCampus) {
            abort(403);
        }

        $maintenance->load(['equipment', 'performer']);

        return Inertia::render('Lab/Maintenance/Show', [
            'maintenance' => $maintenance,
        ]);
    }
}
