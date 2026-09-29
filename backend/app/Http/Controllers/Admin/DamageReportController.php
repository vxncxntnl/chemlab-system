<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\AuditLog;
use App\Models\EquipmentInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DamageReportController extends Controller
{
    public function index(Request $request)
    {
        $query = DamageReport::with(['borrowing.user', 'equipment', 'reporter']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Admin/DamageReports/Index', [
            'reports' => $reports,
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(DamageReport $damageReport)
    {
        $damageReport->load(['borrowing.user', 'borrowing.faculty', 'equipment', 'reporter']);

        return Inertia::render('Admin/DamageReports/Show', [
            'report' => $damageReport,
        ]);
    }

    public function assess(Request $request, DamageReport $damageReport)
    {
        $damageReport->update(['status' => 'assessed']);

        AuditLog::log(auth()->id(), 'Assessed Damage Report', 'damage_reports', $damageReport->id);

        return back()->with('success', 'Damage report assessed.');
    }

    public function resolve(DamageReport $damageReport)
    {
        DB::transaction(function () use ($damageReport) {
            // Only restock once — skip if this report was already resolved before.
            if ($damageReport->status !== 'resolved' && $damageReport->quantity && $damageReport->equipment_inventory_id) {
                $inventory = EquipmentInventory::find($damageReport->equipment_inventory_id);
                if ($inventory) {
                    $inventory->update([
                        'available_quantity' => min(
                            $inventory->total_quantity,
                            $inventory->available_quantity + $damageReport->quantity
                        ),
                    ]);
                }
            }

            $damageReport->update([
                'status' => 'resolved',
                'resolved_date' => now(),
            ]);
        });

        AuditLog::log(auth()->id(), 'Resolved Damage Report', 'damage_reports', $damageReport->id);

        return back()->with('success', 'Damage report resolved — equipment marked available again.');
    }

    public function print(DamageReport $damageReport)
    {
        $damageReport->load([
            'borrowing.user',
            'borrowing.faculty',
            'borrowing.borrowingEquipment.equipment',
            'borrowing.borrowingEquipment.inventory.campus',
            'borrowing.borrowingEquipment.inventory.location',
            'equipment',
            'reporter',
        ]);

        return Inertia::render('DamageReports/Print', [
            'report' => $damageReport,
        ]);
    }
}
