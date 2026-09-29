<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EquipmentInventory;
use App\Models\StockAdjustment;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function store(Request $request, EquipmentInventory $inventory)
    {
        $validated = $request->validate([
            'type'     => 'required|in:in,out',
            'quantity' => 'required|integer|min:1',
            'reason'   => 'required|string|max:255',
        ]);

        if ($validated['type'] === 'out') {
            if ($validated['quantity'] > $inventory->available_quantity) {
                return back()->with('error', 'Cannot remove more than the available quantity (' . $inventory->available_quantity . ').');
            }
            $inventory->decrement('total_quantity', $validated['quantity']);
            $inventory->decrement('available_quantity', $validated['quantity']);
        } else {
            $inventory->increment('total_quantity', $validated['quantity']);
            $inventory->increment('available_quantity', $validated['quantity']);
        }

        StockAdjustment::create([
            'equipment_inventory_id' => $inventory->id,
            'type'                   => $validated['type'],
            'quantity'               => $validated['quantity'],
            'reason'                 => $validated['reason'],
            'performed_by'           => auth()->id(),
        ]);

        AuditLog::log(
            auth()->id(),
            'Stock ' . ($validated['type'] === 'in' ? 'In' : 'Out') . ' — ' . $validated['quantity'] . ' units',
            'equipment_inventory',
            $inventory->id
        );

        return back()->with('success', 'Stock adjusted successfully.');
    }
}
