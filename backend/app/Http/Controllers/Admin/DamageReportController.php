<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\AuditLog;
use App\Models\EquipmentInventory;
use App\Services\DamageReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DamageReportController extends Controller
{
    public function index(Request $request)
    {
        // This admin's campus only.
        $query = \App\Support\CampusAccess::damageReports(DamageReport::query(), \App\Support\CampusAccess::adminCampusId(auth()->user()))
            ->with(['borrowing.user', 'equipment', 'reporter']);

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
        $damageReport->load(['borrowing.user', 'borrowing.faculty', 'equipment', 'reporter', 'rejecter:id,name,role']);

        return Inertia::render('Admin/DamageReports/Show', [
            'report' => $damageReport,
            'history' => app(DamageReportService::class)->history($damageReport),
            'rejectReasons' => DamageReport::REJECT_REASONS,
            'canReopen' => $damageReport->canBeReopened(),
            'reopenDays' => DamageReport::REOPEN_DAYS,
        ]);
    }

    public function update(Request $request, DamageReport $damageReport)
    {
        [$fields, $photo, $note] = DamageReport::editFrom($request);

        app(DamageReportService::class)->edit($damageReport, $fields, $photo, $note, auth()->user());

        return back()->with('success', 'Damage report updated.');
    }

    /**
     * Every action on this campus's damage reports (recorded, assessed,
     * edited, rejected, reopened, resolved), newest first.
     */
    public function history(Request $request)
    {
        $campusReports = \App\Support\CampusAccess::damageReports(DamageReport::query(), \App\Support\CampusAccess::adminCampusId(auth()->user()))->select('id');

        $logs = AuditLog::with('user:id,name,role')
            ->where('table_affected', 'damage_reports')
            ->whereIn('record_id', $campusReports)
            ->when($request->action, fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $reports = DamageReport::with('equipment:id,name', 'borrowing.user:id,name')
            ->whereIn('id', $logs->getCollection()->pluck('record_id'))
            ->get()->keyBy('id');

        $logs->setCollection($logs->getCollection()->map(fn (AuditLog $log) => DamageReportService::historyEntry($log) + [
            'report_id' => $log->record_id,
            'equipment' => $reports[$log->record_id]?->equipment?->name,
            'borrower' => $reports[$log->record_id]?->borrowing?->user?->name,
            'status' => $reports[$log->record_id]?->status,
        ]));

        return Inertia::render('Admin/DamageReports/History', [
            'entries' => $logs,
            'filters' => $request->only(['action']),
        ]);
    }

    public function reject(Request $request, DamageReport $damageReport)
    {
        $reason = DamageReport::rejectionReasonFrom($request);

        app(DamageReportService::class)->reject($damageReport, $reason, auth()->user());

        return back()->with('success', 'Damage report rejected — no replacement required.');
    }

    public function reopen(DamageReport $damageReport)
    {
        try {
            app(DamageReportService::class)->reopen($damageReport, auth()->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Damage report reopened.');
    }

    public function assess(Request $request, DamageReport $damageReport)
    {
        // A rejected report comes back only through Reopen (which also
        // takes back the stock the rejection returned).
        if ($damageReport->status === 'rejected') {
            return back()->with('error', 'This report was rejected. Reopen it first.');
        }

        $damageReport->update(['status' => 'assessed']);

        AuditLog::log(auth()->id(), 'Assessed Damage Report', 'damage_reports', $damageReport->id);

        return back()->with('success', 'Damage report assessed.');
    }

    public function resolve(DamageReport $damageReport)
    {
        // Rejecting already put its units back in stock — resolving it too
        // would add them a second time.
        if ($damageReport->status === 'rejected') {
            return back()->with('error', 'This report was rejected. Reopen it first.');
        }

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

    /**
     * Streams the damage photo to an authenticated admin only — never a
     * public URL, since the photo may show a student's borrowing context.
     */
    public function photo(DamageReport $damageReport)
    {
        if (!$damageReport->photo_path || !Storage::disk('local')->exists($damageReport->photo_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($damageReport->photo_path);
    }

    public function print(DamageReport $damageReport)
    {
        $damageReport->load([
            'borrowing.user',
            'borrowing.faculty',
            'borrowing.borrowingEquipment.equipment',
            'borrowing.borrowingEquipment.inventory.campus',
            'borrowing.borrowingEquipment.inventory.location',
            // The printed report lists only the damaged items of this
            // borrowing — its damage reports (one per damaged item) — not
            // every item that was borrowed.
            'borrowing.damageReports.equipment',
            'borrowing.damageReports.inventory.campus',
            'borrowing.damageReports.inventory.location',
            'equipment',
            'reporter',
        ]);

        return Inertia::render('DamageReports/Print', [
            'report' => $damageReport,
        ]);
    }
}
