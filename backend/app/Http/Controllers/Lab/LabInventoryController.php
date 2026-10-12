<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LabInventory;
use App\Models\LabInventoryLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LabInventoryController extends Controller
{
    public function index(Request $request)
    {
        // The masterfile is the Carmen main lab's (see User::managesLabMasterfile).
        abort_unless(auth()->user()->managesLabMasterfile(), 403);

        $user = auth()->user();
        $campusName = $user->campus?->campus_name ?? '';

        // Determine which lab column to filter by based on campus name
        $labFilter = null;
        if (stripos($campusName, 'iligan') !== false) {
            $labFilter = 'iligan';
        } elseif (stripos($campusName, 'butuan') !== false || stripos($campusName, 'puerto') !== false) {
            $labFilter = 'butuan';
        }

        $query = LabInventory::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereLike('description', "%{$request->search}%")
                    ->orWhereLike('item_no', "%{$request->search}%")
                    ->orWhereLike('brand', "%{$request->search}%");
            });
        }

        if ($request->lab) {
            $query->where('lab', $request->lab);
        }

        $query->orderBy('description');

        $inventory = $query->paginate(15)->withQueryString();

        $stats = [
            'total_items'      => LabInventory::count(),
            'chemistry_items'  => LabInventory::chemistry()->count(),
            'physics_items'    => LabInventory::physics()->count(),
            'low_stock'        => LabInventory::where('quantity', '<=', 5)->where('quantity', '>', 0)->count(),
            'out_of_stock'     => LabInventory::where('quantity', 0)->count(),
        ];

        return Inertia::render('Lab/LabInventory/Index', [
            'inventory'   => $inventory,
            'stats'       => $stats,
            'labFilter'   => $labFilter,
            'filters'     => $request->only(['search', 'lab']),
        ]);
    }

    public function update(Request $request, LabInventory $labInventory)
    {
        abort_unless(auth()->user()->managesLabMasterfile(), 403);

        $validated = $request->validate([
            'quantity'          => 'required|integer|min:0',
            'condition_remarks' => 'nullable|string|max:255',
        ]);

        $oldValues = $labInventory->only(['quantity', 'condition_remarks']);

        $labInventory->update($validated);

        LabInventoryLog::create([
            'inventory_id' => $labInventory->id,
            'user_id' => auth()->id(),
            'action' => 'updated',
            'changes' => ['old' => $oldValues, 'new' => $validated],
        ]);

        AuditLog::log(auth()->id(), 'Updated Lab Inventory Item (Lab Staff)', 'lab_inventory', $labInventory->id);

        return back()->with('success', 'Inventory item updated successfully.');
    }
}
