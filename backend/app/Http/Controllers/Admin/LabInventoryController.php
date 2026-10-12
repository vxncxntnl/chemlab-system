<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabInventory;
use App\Models\LabInventoryLog;
use App\Models\LabInventoryTransfer;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LabInventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = LabInventory::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereLike('description', "%{$request->search}%")
                    ->orWhereLike('item_no', "%{$request->search}%")
                    ->orWhereLike('storage_location', "%{$request->search}%")
                    ->orWhereLike('brand', "%{$request->search}%");
            });
        }

        if ($request->lab) {
            $query->where('lab', $request->lab);
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        if ($request->condition) {
            switch ($request->condition) {
                case 'good':
                    $query->whereLike('condition_remarks', '%Good%')
                        ->orWhereLike('condition_remarks', '%Working%');
                    break;
                case 'defective':
                    $query->whereLike('condition_remarks', '%Broken%')
                        ->orWhereLike('condition_remarks', '%Damage%')
                        ->orWhereLike('condition_remarks', '%Not Working%');
                    break;
                case 'needs_repair':
                    $query->whereLike('condition_remarks', '%repair%');
                    break;
                case 'for_disposal':
                    $query->whereLike('condition_remarks', '%Disposal%');
                    break;
            }
        }

        if ($request->storage_location) {
            $query->where('storage_location', $request->storage_location);
        }

        $sortField = $request->sort_by ?? 'description';
        $sortDirection = $request->sort_dir ?? 'asc';
        $query->orderBy($sortField, $sortDirection);

        $inventory = $query->paginate(15)->withQueryString();

        $stats = [
            'total_items' => LabInventory::count(),
            'chemistry_items' => LabInventory::chemistry()->count(),
            'physics_items' => LabInventory::physics()->count(),
            'general_items' => LabInventory::general()->count(),
            'pasco_items' => LabInventory::pasco()->count(),
            'transferred_iligan' => LabInventory::where('qty_sent_iligan', '>', 0)->count(),
            'transferred_butuan' => LabInventory::where('qty_sent_butuan', '>', 0)->count(),
        ];

        $storageLocations = LabInventory::distinct()
            ->whereNotNull('storage_location')
            ->pluck('storage_location')
            ->filter();

        return Inertia::render('Admin/LabInventory/Index', [
            'inventory' => $inventory,
            'stats' => $stats,
            'storageLocations' => $storageLocations,
            'filters' => $request->only(['search', 'lab', 'category', 'condition', 'storage_location', 'sort_by', 'sort_dir']),
        ]);
    }

    public function dashboard()
    {
        $stats = [
            'total_items' => LabInventory::count(),
            'chemistry_total' => LabInventory::chemistry()->count(),
            'physics_total' => LabInventory::physics()->count(),
            'chemistry_general' => LabInventory::chemistry()->general()->count(),
            'chemistry_pasco' => LabInventory::chemistry()->pasco()->count(),
            'physics_general' => LabInventory::physics()->general()->count(),
            'physics_pasco' => LabInventory::physics()->pasco()->count(),
            'good_condition' => LabInventory::goodCondition()->count(),
            'defective' => LabInventory::defective()->count(),
            'needs_repair' => LabInventory::needsRepair()->count(),
            'transferred_iligan' => LabInventory::sum('qty_sent_iligan'),
            'transferred_butuan' => LabInventory::sum('qty_sent_butuan'),
        ];

        $recentTransfers = LabInventoryTransfer::with('inventory')
            ->latest()
            ->take(10)
            ->get();

        $lowQuantityItems = LabInventory::where('quantity', '<=', 5)
            ->where('quantity', '>', 0)
            ->take(10)
            ->get();

        return Inertia::render('Admin/LabInventory/Dashboard', [
            'stats' => $stats,
            'recentTransfers' => $recentTransfers,
            'lowQuantityItems' => $lowQuantityItems,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/LabInventory/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lab' => 'required|in:chemistry,physics',
            'category' => 'required|in:general,pasco',
            'item_no' => 'nullable|string|max:20',
            'description' => 'required|string|max:255',
            'size' => 'nullable|string|max:100',
            'quantity' => 'required|integer|min:0',
            'uom' => 'nullable|string|max:50',
            'brand' => 'nullable|string|max:100',
            'storage_location' => 'nullable|string|max:100',
            'condition_remarks' => 'nullable|string',
            'qty_sent_iligan' => 'integer|min:0',
            'qty_sent_butuan' => 'integer|min:0',
            'current_campus_location' => 'nullable|string|max:100',
        ]);

        $item = LabInventory::create($validated);

        LabInventoryLog::create([
            'inventory_id' => $item->id,
            'user_id' => auth()->id(),
            'action' => 'created',
            'changes' => $validated,
        ]);

        AuditLog::log(auth()->id(), 'Created Lab Inventory Item', 'lab_inventory', $item->id);

        return redirect()->route('admin.lab-inventory.index')
            ->with('success', 'Lab inventory item created successfully.');
    }

    public function show(LabInventory $labInventory)
    {
        $labInventory->load(['transfers', 'logs.user']);

        return Inertia::render('Admin/LabInventory/Show', [
            'item' => $labInventory,
        ]);
    }

    public function edit(LabInventory $labInventory)
    {
        return Inertia::render('Admin/LabInventory/Edit', [
            'item' => $labInventory,
        ]);
    }

    public function update(Request $request, LabInventory $labInventory)
    {
        $validated = $request->validate([
            'lab' => 'required|in:chemistry,physics',
            'category' => 'required|in:general,pasco',
            'item_no' => 'nullable|string|max:20',
            'description' => 'required|string|max:255',
            'size' => 'nullable|string|max:100',
            'quantity' => 'required|integer|min:0',
            'uom' => 'nullable|string|max:50',
            'brand' => 'nullable|string|max:100',
            'storage_location' => 'nullable|string|max:100',
            'condition_remarks' => 'nullable|string',
            'qty_sent_iligan' => 'integer|min:0',
            'qty_sent_butuan' => 'integer|min:0',
            'current_campus_location' => 'nullable|string|max:100',
        ]);

        $oldValues = $labInventory->toArray();

        $labInventory->update($validated);

        LabInventoryLog::create([
            'inventory_id' => $labInventory->id,
            'user_id' => auth()->id(),
            'action' => 'updated',
            'changes' => [
                'old' => $oldValues,
                'new' => $validated,
            ],
        ]);

        AuditLog::log(auth()->id(), 'Updated Lab Inventory Item', 'lab_inventory', $labInventory->id);

        return redirect()->route('admin.lab-inventory.index')
            ->with('success', 'Lab inventory item updated successfully.');
    }

    public function destroy(LabInventory $labInventory)
    {
        LabInventoryLog::create([
            'inventory_id' => $labInventory->id,
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'changes' => $labInventory->toArray(),
        ]);

        AuditLog::log(auth()->id(), 'Deleted Lab Inventory Item', 'lab_inventory', $labInventory->id);

        $labInventory->delete();

        return redirect()->route('admin.lab-inventory.index')
            ->with('success', 'Lab inventory item deleted successfully.');
    }

    public function transfer(Request $request, LabInventory $labInventory)
    {
        $validated = $request->validate([
            'campus' => 'required|in:iligan,butuan',
            'quantity' => 'required|integer|min:1',
            'transfer_date' => 'required|date',
            'received_by' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $availableQty = $labInventory->quantity - $labInventory->qty_sent_iligan - $labInventory->qty_sent_butuan;

        if ($validated['quantity'] > $availableQty) {
            return back()->withErrors(['quantity' => 'Insufficient quantity available for transfer.']);
        }

        LabInventoryTransfer::create([
            'inventory_id' => $labInventory->id,
            'campus' => $validated['campus'],
            'quantity' => $validated['quantity'],
            'transfer_date' => $validated['transfer_date'],
            'received_by' => $validated['received_by'],
            'notes' => $validated['notes'],
        ]);

        if ($validated['campus'] === 'iligan') {
            $labInventory->increment('qty_sent_iligan', $validated['quantity']);
        } else {
            $labInventory->increment('qty_sent_butuan', $validated['quantity']);
        }

        LabInventoryLog::create([
            'inventory_id' => $labInventory->id,
            'user_id' => auth()->id(),
            'action' => 'updated',
            'changes' => [
                'transfer' => $validated,
            ],
        ]);

        AuditLog::log(auth()->id(), 'Transferred Lab Inventory', 'lab_inventory', $labInventory->id);

        return back()->with('success', 'Transfer recorded successfully.');
    }

    public function exportExcel(Request $request)
    {
        // This would use Laravel Excel (maatwebsite/excel)
        // For now, return a placeholder response
        return back()->with('info', 'Export functionality requires maatwebsite/excel package.');
    }
}
