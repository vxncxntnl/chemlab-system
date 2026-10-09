<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\EquipmentInventory;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        // This admin's campus only (the campus filter can't widen it).
        $campusId = \App\Support\CampusAccess::adminCampusId(auth()->user());
        $query = EquipmentInventory::with(['equipment', 'campus', 'location'])->where('campus_id', $campusId);

        if ($request->low_stock) {
            $query->whereColumn('available_quantity', '<=', 'reorder_level');
        }

        $inventory = $query->paginate(15);
        $campuses = Campus::whereKey($campusId)->get();

        return Inertia::render('Admin/Inventory/Index', [
            'inventory' => $inventory,
            'campuses' => $campuses,
            'filters' => $request->only(['campus_id', 'low_stock']),
        ]);
    }

    public function update(Request $request, EquipmentInventory $inventory)
    {
        $validated = $request->validate([
            'total_quantity' => 'required|integer|min:0',
            'available_quantity' => 'required|integer|min:0|lte:total_quantity',
            'reorder_level' => 'required|integer|min:0',
        ]);

        $oldValues = $inventory->only(['total_quantity', 'available_quantity', 'reorder_level']);

        $inventory->update($validated);

        AuditLog::log(
            auth()->id(),
            'Updated Inventory',
            'equipment_inventory',
            $inventory->id,
            $oldValues,
            $validated
        );

        return back()->with('success', 'Inventory updated successfully.');
    }
}
