<?php

namespace App\Http\Controllers\Faculty;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityEquipmentQr;
use App\Models\AuditLog;
use App\Models\EquipmentInventory;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with(['students'])
            // Tells the list whether to offer Delete (no requests yet) or
            // Cancel (has requests, so its history must be kept).
            ->withCount(['borrowings as requests_count' => fn ($q) => $q->withTrashed()])
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

    /**
     * Equipment a faculty member may attach to an activity: their own
     * campus only — the same hard restriction Browse Equipment and New
     * Request already apply. An activity being edited keeps showing items
     * it already has, so saving doesn't silently drop them. Legacy faculty
     * accounts with no campus set keep the previous all-campus list.
     */
    private function selectableInventory(?Activity $activity = null)
    {
        $campusId = auth()->user()->campus_id;
        $attachedIds = $activity ? $activity->equipmentQrCodes->pluck('equipment_inventory_id')->all() : [];

        return EquipmentInventory::with(['equipment', 'campus', 'location'])
            ->whereHas('equipment')
            ->when($campusId, fn ($q) => $q->where(fn ($q) => $q
                ->where('campus_id', $campusId)
                ->orWhereIn('id', $attachedIds)))
            ->get();
    }

    private function inventoryRules(?Activity $activity = null): array
    {
        $allowedIds = $this->selectableInventory($activity)->pluck('id')->all();

        return [
            'equipment_inventory_ids'   => 'nullable|array',
            'equipment_inventory_ids.*' => [Rule::in($allowedIds)],
        ];
    }

    private const INVENTORY_MESSAGES = [
        'equipment_inventory_ids.*.in' => 'You can only add equipment from your own campus.',
        'return_date.after_or_equal' => 'The return date must be on or after the borrow date.',
    ];

    public function create()
    {
        $inventoryItems = $this->selectableInventory();

        return Inertia::render('Faculty/Activities/Create', [
            'inventoryItems' => $inventoryItems,
            'facultyDepartment' => auth()->user()->department,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            // Taken from the instructor's own program when set (see below);
            // only chosen on the form if their account has none.
            'department'   => [auth()->user()->department ? 'nullable' : 'required', 'string', 'max:255'],
            'activity_date'=> 'required|date',
            'return_date'  => 'required|date|after_or_equal:activity_date',
            'status'       => 'required|in:upcoming,ongoing,completed,cancelled,expired',
            'description'  => 'nullable|string|max:1000',
        ] + $this->inventoryRules(), self::INVENTORY_MESSAGES);

        $validated['instructor_id'] = auth()->id();

        $activity = Activity::create([
            'title'         => $validated['title'],
            'instructor_id' => $validated['instructor_id'],
            'department'    => auth()->user()->department ?: $validated['department'],
            'activity_date' => $validated['activity_date'],
            'return_date'   => $validated['return_date'],
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
            // The activity's single QR code (only its instructor sees it).
            'qrUrl' => $activity->qrScanUrl(),
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

        if ($activity->status === 'cancelled') {
            return redirect()->route('faculty.activities.show', $activity)
                ->with('error', 'This activity was cancelled and can no longer be edited.');
        }

        $activity->load(['students', 'equipmentQrCodes']);
        $inventoryItems = $this->selectableInventory($activity);

        return Inertia::render('Faculty/Activities/Edit', [
            'activity' => $activity,
            'inventoryItems' => $inventoryItems,
            'selectedInventoryIds' => $activity->equipmentQrCodes->pluck('equipment_inventory_id'),
            'facultyDepartment' => auth()->user()->department,
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

        if ($activity->status === 'cancelled') {
            return redirect()->route('faculty.activities.show', $activity)
                ->with('error', 'This activity was cancelled and can no longer be edited.');
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            // Taken from the instructor's own program when set (see below);
            // only chosen on the form if their account has none.
            'department'   => [auth()->user()->department ? 'nullable' : 'required', 'string', 'max:255'],
            'activity_date'=> 'required|date',
            'return_date'  => 'required|date|after_or_equal:activity_date',
            'status'       => 'required|in:upcoming,ongoing,completed,cancelled,expired',
            'description'  => 'nullable|string|max:1000',
        ] + $this->inventoryRules($activity), self::INVENTORY_MESSAGES);

        $activity->update([
            'title'         => $validated['title'],
            'department'    => auth()->user()->department ?: $validated['department'],
            'activity_date' => $validated['activity_date'],
            'return_date'   => $validated['return_date'],
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

    public function destroy(Activity $activity, ActivityService $activities)
    {
        if ($activity->instructor_id !== auth()->id()) {
            abort(403);
        }

        // Deleting would unlink (not cancel) its requests and erase its
        // history; only an activity nobody has borrowed for can be deleted.
        if ($activities->hasAnyRequests($activity)) {
            return back()->with('error', 'This activity already has borrowing requests, so it can\'t be deleted. Use Cancel Activity instead — it keeps the history and cancels requests that haven\'t been released.');
        }

        AuditLog::log(auth()->id(), 'Deleted Activity', 'activities', $activity->id, $activity->toArray());
        $activity->delete();

        return redirect()->route('faculty.activities.index')
            ->with('success', 'Activity deleted.');
    }

    public function cancel(Activity $activity, ActivityService $activities)
    {
        if ($activity->instructor_id !== auth()->id()) {
            abort(403);
        }

        if (in_array($activity->status, ['cancelled', 'completed', 'expired'], true)) {
            return back()->with('error', "This activity is already {$activity->status}.");
        }

        try {
            $count = $activities->cancel($activity, auth()->user());
        } catch (\App\Exceptions\InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('faculty.activities.show', $activity)
            ->with('success', $count
                ? "Activity cancelled. {$count} borrowing request(s) were cancelled and the students were notified."
                : 'Activity cancelled.');
    }
}
