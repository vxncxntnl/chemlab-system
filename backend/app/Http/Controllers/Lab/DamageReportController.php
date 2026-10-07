<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\AuditLog;
use App\Models\EquipmentInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DamageReportController extends Controller
{
    /**
     * Lab staff may only act on damage reports whose equipment is stocked at
     * their own campus. index()/show()/print() already enforced this for
     * reads; assess()/resolve() are the actual state-changing actions and
     * must enforce it too, or a lab staff account at any campus could act on
     * another campus's reports (and, via resolve(), restock that campus's
     * inventory based on a report they had no real connection to).
     */
    private function abortUnlessOwnCampus(DamageReport $damageReport): void
    {
        $campusId = auth()->user()->campus_id;

        if (!$damageReport->borrowing?->borrowingEquipment()->whereHas('inventory', function ($q) use ($campusId) {
            $q->where('campus_id', $campusId);
        })->exists()) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $campusId = auth()->user()->campus_id;

        $query = DamageReport::with(['borrowing.user', 'equipment', 'reporter'])
            ->whereHas('borrowing.borrowingEquipment.inventory', function ($q) use ($campusId) {
                $q->where('campus_id', $campusId);
            });

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Lab/DamageReports/Index', [
            'reports' => $reports,
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(DamageReport $damageReport)
    {
        $this->abortUnlessOwnCampus($damageReport);

        $damageReport->load(['borrowing.user', 'borrowing.faculty', 'equipment', 'reporter']);

        return Inertia::render('Lab/DamageReports/Show', [
            'report' => $damageReport,
        ]);
    }

    public function assess(Request $request, DamageReport $damageReport)
    {
        $this->abortUnlessOwnCampus($damageReport);

        // mimes: checks the file's actual content (magic bytes), not the
        // extension or the browser-supplied Content-Type — a renamed
        // .php/.html/.js file fails this regardless of its filename.
        $validated = $request->validate([
            'photo' => 'nullable|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $updates = ['status' => 'assessed'];

        if ($request->hasFile('photo')) {
            // Private 'local' disk, not 'public' — unlike equipment photos,
            // this must not be reachable by anyone with a guessed URL.
            // ->store() also generates its own random filename, so the
            // original filename the browser sent is never trusted/used.
            if ($damageReport->photo_path) {
                Storage::disk('local')->delete($damageReport->photo_path);
            }
            $updates['photo_path'] = $request->file('photo')->store('damage-reports', 'local');
        }

        $damageReport->update($updates);

        AuditLog::log(auth()->id(), 'Assessed Damage Report (Lab Staff)', 'damage_reports', $damageReport->id);

        return back()->with('success', 'Damage report assessed.');
    }

    public function resolve(DamageReport $damageReport)
    {
        $this->abortUnlessOwnCampus($damageReport);

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

        AuditLog::log(auth()->id(), 'Resolved Damage Report (Lab Staff)', 'damage_reports', $damageReport->id);

        return back()->with('success', 'Damage report resolved — equipment marked available again.');
    }

    /**
     * Streams the damage photo to an authenticated, campus-scoped lab staff
     * member only — never a public URL, since the photo may show a
     * student's borrowing context.
     */
    public function photo(DamageReport $damageReport)
    {
        $this->abortUnlessOwnCampus($damageReport);

        if (!$damageReport->photo_path || !Storage::disk('local')->exists($damageReport->photo_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($damageReport->photo_path);
    }

    public function print(DamageReport $damageReport)
    {
        $this->abortUnlessOwnCampus($damageReport);

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

