<?php

namespace App\Http\Controllers\Faculty;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityEquipmentQr;
use App\Models\AuditLog;
use App\Models\EquipmentInventory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with(['students'])
            ->where('instructor_id', auth()->id());

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $activities = $query->orderBy('activity_date', 'desc')->paginate(15);

        return Inertia::render('Faculty/Activities/Index', [
            'activities' => $activities,
            'filters'    => $request->only(['status']),
        ]);
    }

    public function create()
    {
        $inventoryItems = EquipmentInventory::with(['equipment', 'campus', 'location'])
            ->whereHas('equipment')
            ->get();

        return Inertia::render('Faculty/Activities/Create', [
            'inventoryItems' => $inventoryItems,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'department'   => 'required|string|max:255',
            'activity_date'=> 'required|date',
            'status'       => 'required|in:upcoming,ongoing,completed,cancelled,expired',
            'description'  => 'nullable|string|max:1000',
            'equipment_inventory_ids'   => 'nullable|array',
            'equipment_inventory_ids.*' => 'exists:equipment_inventory,id',
        ]);

        $validated['instructor_id'] = auth()->id();

        $activity = Activity::create([
            'title'         => $validated['title'],
            'instructor_id' => $validated['instructor_id'],
            'department'    => $validated['department'],
            'activity_date' => $validated['activity_date'],
            'status'        => $validated['status'],
            'description'   => $validated['description'] ?? null,
        ]);

        foreach ($validated['equipment_inventory_ids'] ?? [] as $inventoryId) {
            ActivityEquipmentQr::findOrCreateFor($activity->id, $inventoryId);
        }

        AuditLog::log(auth()->id(), 'Created Activity', 'activities', $activity->id, null, $activity->toArray());

        return redirect()->route('faculty.activities.index')
            ->with('success', 'Activity created successfully.');
    }

    public function show(Activity $activity)
    {
        if ($activity->instructor_id !== auth()->id()) {
            abort(403);
        }

        $activity->load([
            'students',
            'borrowings.user',
            'borrowings.borrowingEquipment.equipment',
            'equipmentQrCodes.inventory.equipment',
            'equipmentQrCodes.inventory.campus',
            'equipmentQrCodes.inventory.location',
        ]);

        return Inertia::render('Faculty/Activities/Show', [
            'activity' => $activity,
        ]);
    }

    public function edit(Activity $activity)
    {
        if ($activity->instructor_id !== auth()->id()) {
            abort(403);
        }

        if ($activity->isExpired()) {
            return redirect()->route('faculty.activities.show', $activity)
                ->with('error', 'This activity has expired and can no longer be edited.');
        }

        $activity->load(['students', 'equipmentQrCodes']);
        $inventoryItems = EquipmentInventory::with(['equipment', 'campus', 'location'])
            ->whereHas('equipment')
            ->get();

        return Inertia::render('Faculty/Activities/Edit', [
            'activity' => $activity,
            'inventoryItems' => $inventoryItems,
            'selectedInventoryIds' => $activity->equipmentQrCodes->pluck('equipment_inventory_id'),
        ]);
    }

    public function update(Request $request, Activity $activity)
    {
        if ($activity->instructor_id !== auth()->id()) {
            abort(403);
        }

        if ($activity->isExpired()) {
            return redirect()->route('faculty.activities.show', $activity)
                ->with('error', 'This activity has expired and can no longer be edited.');
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'department'   => 'required|string|max:255',
            'activity_date'=> 'required|date',
            'status'       => 'required|in:upcoming,ongoing,completed,cancelled,expired',
            'description'  => 'nullable|string|max:1000',
            'equipment_inventory_ids'   => 'nullable|array',
            'equipment_inventory_ids.*' => 'exists:equipment_inventory,id',
        ]);

        $activity->update([
            'title'         => $validated['title'],
            'department'    => $validated['department'],
            'activity_date' => $validated['activity_date'],
            'status'        => $validated['status'],
            'description'   => $validated['description'] ?? null,
        ]);

        $selectedIds = $validated['equipment_inventory_ids'] ?? [];
        $activity->equipmentQrCodes()->whereNotIn('equipment_inventory_id', $selectedIds)->delete();
        foreach ($selectedIds as $inventoryId) {
            ActivityEquipmentQr::findOrCreateFor($activity->id, $inventoryId);
        }

        AuditLog::log(auth()->id(), 'Updated Activity', 'activities', $activity->id, null, $activity->toArray());

        return redirect()->route('faculty.activities.index')
            ->with('success', 'Activity updated successfully.');
    }

    public function destroy(Activity $activity)
    {
        if ($activity->instructor_id !== auth()->id()) {
            abort(403);
        }

        $activity->delete();

        return redirect()->route('faculty.activities.index')
            ->with('success', 'Activity deleted.');
    }
}
