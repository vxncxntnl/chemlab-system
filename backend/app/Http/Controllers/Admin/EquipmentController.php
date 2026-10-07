<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Equipment::with('inventory.campus', 'inventory.location');

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
        $categories = Equipment::distinct()->pluck('category')->filter();

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
        $campuses = Campus::orderBy('id')->get(['id', 'campus_name']);
        $campusId = $request->filled('campus_id')
            ? (int) $request->campus_id
            : (auth()->user()->campus_id ?? $campuses->first()?->id);
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
        return Inertia::render('Admin/Equipment/Create', [
            'campuses' => Campus::with('locations')->get(),
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
            'inventory' => 'required|array|min:1',
            'inventory.*.campus_id' => 'required|exists:campuses,id',
            'inventory.*.location_id' => 'required|exists:locations,id',
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

        foreach ($validated['inventory'] as $inv) {
            EquipmentInventory::create([
                'equipment_id' => $equipment->id,
                'campus_id' => $inv['campus_id'],
                'location_id' => $inv['location_id'],
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
        $equipment->load([
            'inventory.campus',
            'inventory.location',
            'inventory.stockAdjustments.performer',
            'borrowingEquipment.borrowing.user',
        ]);

        return Inertia::render('Admin/Equipment/Show', [
            'equipment' => $equipment,
        ]);
    }

    public function edit(Equipment $equipment)
    {
        $equipment->load('inventory');

        return Inertia::render('Admin/Equipment/Edit', [
            'equipment' => $equipment,
            'campuses' => Campus::with('locations')->get(),
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
