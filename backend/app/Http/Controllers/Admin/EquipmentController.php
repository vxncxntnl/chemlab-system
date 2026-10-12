<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\AuditLog;
use App\Support\CampusAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        // Equipment stocked at this admin's campus, with that campus's stock only.
        $admin = auth()->user();
        $campusId = CampusAccess::adminCampusId($admin);
        $query = CampusAccess::equipment(Equipment::query(), $campusId, CampusAccess::isMainAdmin($admin))
            ->with(['inventory' => fn ($q) => $q->where('campus_id', $campusId)->with('campus', 'location')])
            // Serial-tracked items: how many units this campus has.
            ->withCount(['units' => fn ($q) => $q->whereHas('inventory', fn ($i) => $i->where('campus_id', $campusId))]);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('category', 'like', "%{$request->search}%")
                    ->orWhere('serial_number', 'like', "%{$request->search}%");
            });
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        $equipment = $query->paginate(10);
        $categories = CampusAccess::equipment(Equipment::query(), $campusId)->distinct()->pluck('category')->filter();

        return Inertia::render('Admin/Equipment/Index', [
            'equipment' => $equipment,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    /**
     * Printable equipment report for the lab in-charge — same filters as
     * the list page, but unpaginated (a report should show everything
     * matching, not just one page of it).
     */
    public function report(Request $request)
    {
        // The report covers one campus: the one picked on the report, else
        // the admin's own campus, else the first campus set up. Only
        // equipment stocked there is listed, with that campus's quantities.
        // Each size is its own equipment item (e.g. Beaker 250 mL vs 500 mL).
        // Always the admin's own campus.
        $campusId = CampusAccess::adminCampusId(auth()->user());
        $campuses = Campus::whereKey($campusId)->get(['id', 'campus_name']);
        $atCampus = fn ($q) => $q->where('campus_id', $campusId);

        $query = Equipment::query()
            ->whereHas('inventory', $atCampus)
            ->withSum(['inventory as total_quantity' => $atCampus], 'total_quantity')
            ->withSum(['inventory as available_quantity' => $atCampus], 'available_quantity');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('category', 'like', "%{$request->search}%")
                    ->orWhere('serial_number', 'like', "%{$request->search}%");
            });
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        $equipment = $query->orderBy('equipment_code')->get();

        return Inertia::render('Admin/Equipment/Report', [
            'equipment' => $equipment,
            'filters' => $request->only(['search', 'category']),
            'campuses' => $campuses,
            'campus' => $campuses->firstWhere('id', $campusId),
        ]);
    }

    public function create()
    {
        // New stock always goes to the admin's own campus (its Lab Storage Room).
        return Inertia::render('Admin/Equipment/Create', [
            'campuses' => Campus::whereKey(CampusAccess::adminCampusId(auth()->user()))->get(),
            'categories' => Category::active()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100|unique:equipment',
            'condition' => 'required|in:new,good,fair,poor,damaged',
            'course_restriction' => 'nullable|string|max:500',
            // Explicit raster-only whitelist instead of the generic "image"
            // rule, which also accepts SVG — an XML format that can carry an
            // embedded <script>, making it a stored-XSS vector if the file
            // is ever opened directly rather than shown inside an <img>.
            'image' => 'nullable|mimes:jpg,jpeg,png,webp|max:10240', // up to 10 MB (phone photos)
            // One stock entry, at the admin's own campus — campus and room
            // are set here, never taken from the form.
            'inventory' => 'required|array|size:1',
            'inventory.*.total_quantity' => 'required|integer|min:1',
            'inventory.*.reorder_level' => 'required|integer|min:0',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('equipment', 'public');
        } else {
            // Inherit image from another equipment with the same name if one exists
            $existing = Equipment::where('name', $validated['name'])->whereNotNull('image_path')->first();
            $imagePath = $existing?->image_path;
        }

        $equipment = Equipment::createWithUniqueCode([
            'name' => $validated['name'],
            // These are all "nullable" validation rules, so Laravel's
            // $validated array simply omits the key when the request didn't
            // send it at all — the frontend always sends empty strings for
            // these, but a direct API request that omits them entirely
            // would otherwise throw here instead of just defaulting to null.
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'condition' => $validated['condition'],
            'course_restriction' => $validated['course_restriction'] ?? null,
            'image_path' => $imagePath,
            'is_available' => true,
        ]);

        $campusId = CampusAccess::adminCampusId(auth()->user());
        foreach ($validated['inventory'] as $inv) {
            EquipmentInventory::create([
                'equipment_id' => $equipment->id,
                'campus_id' => $campusId,
                'location_id' => CampusAccess::storageRoomId($campusId),
                'total_quantity' => $inv['total_quantity'],
                'available_quantity' => $inv['total_quantity'],
                'reorder_level' => $inv['reorder_level'],
            ]);
        }

        AuditLog::log(auth()->id(), 'Created Equipment', 'equipment', $equipment->id);

        return redirect()->route('admin.equipment.index')
            ->with('success', 'Equipment created successfully.');
    }

    public function show(Equipment $equipment)
    {
        // Only this admin's campus stock and borrowing history.
        $campusId = CampusAccess::adminCampusId(auth()->user());
        $atCampus = fn ($q) => $q->where('campus_id', $campusId);
        $equipment->load([
            'inventory' => fn ($q) => $atCampus($q)->with(['campus', 'location', 'stockAdjustments.performer']),
            'borrowingEquipment' => fn ($q) => $q->whereHas('inventory', $atCampus)->with('borrowing.user'),
        ]);

        return Inertia::render('Admin/Equipment/Show', [
            'equipment' => $equipment,
            // Serial-tracked items: each unit at this campus, and whether it is under maintenance now.
            'units' => $equipment->tracks_units
                ? $equipment->units()->whereHas('inventory', $atCampus)->with('openMaintenance')->get()->map(fn (\App\Models\EquipmentUnit $u) => [
                    'id' => $u->id,
                    'unit_code' => $u->unit_code,
                    'serial_number' => $u->serial_number,
                    'notes' => $u->notes,
                    'maintenance' => $u->openMaintenance ? [
                        'id' => $u->openMaintenance->id,
                        'type' => $u->openMaintenance->type,
                        'status' => $u->openMaintenance->status,
                        'maintenance_date' => $u->openMaintenance->maintenance_date,
                    ] : null,
                ])
                : [],
        ]);
    }

    public function edit(Equipment $equipment)
    {
        $campusId = CampusAccess::adminCampusId(auth()->user());
        $equipment->load(['inventory' => fn ($q) => $q->where('campus_id', $campusId)]);

        return Inertia::render('Admin/Equipment/Edit', [
            'equipment' => $equipment,
            'campuses' => Campus::with('locations')->whereKey($campusId)->get(),
            'categories' => Category::active()->get(),
        ]);
    }

    public function update(Request $request, Equipment $equipment)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100|unique:equipment,serial_number,' . $equipment->id,
            'condition' => 'required|in:new,good,fair,poor,damaged',
            'is_available' => 'boolean',
            // Track each unit by serial number (instruments, electronics).
            'tracks_units' => 'boolean',
            'course_restriction' => 'nullable|string|max:500',
            // Explicit raster-only whitelist instead of the generic "image"
            // rule, which also accepts SVG — an XML format that can carry an
            // embedded <script>, making it a stored-XSS vector if the file
            // is ever opened directly rather than shown inside an <img>.
            'image' => 'nullable|mimes:jpg,jpeg,png,webp|max:10240', // up to 10 MB (phone photos)
        ]);

        if ($request->hasFile('image')) {
            if ($equipment->image_path) {
                Storage::disk('public')->delete($equipment->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('equipment', 'public');

            // Sync this new image to all other equipment with the same name
            Equipment::where('name', $equipment->name)
                ->where('id', '!=', $equipment->id)
                ->update(['image_path' => $validated['image_path']]);
        }

        $equipment->update($validated);
        // Just switched to serial tracking: one unit per item in stock.
        EquipmentUnitController::createMissingUnits($equipment);

        AuditLog::log(auth()->id(), 'Updated Equipment', 'equipment', $equipment->id);

        return redirect()->route('admin.equipment.index')
            ->with('success', 'Equipment updated successfully.');
    }

    public function destroy(Equipment $equipment)
    {
        if ($equipment->image_path) {
            Storage::disk('public')->delete($equipment->image_path);
        }

        AuditLog::log(auth()->id(), 'Deleted Equipment', 'equipment', $equipment->id);

        $equipment->delete();

        return redirect()->route('admin.equipment.index')
            ->with('success', 'Equipment deleted successfully.');
    }
}
