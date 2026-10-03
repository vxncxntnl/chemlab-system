<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ActivityEquipmentQr;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Activity;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\BorrowingService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BorrowingController extends Controller
{
    public function __construct(
        private BorrowingService $borrowingService,
        private InventoryService $inventoryService
    ) {
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Borrowing::with(['equipment', 'approver', 'faculty'])
            ->where('user_id', $user->id);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $borrowings = $query->orderBy('created_at', 'desc')->paginate(10);

        return Inertia::render('Student/Borrowings/Index', [
            'borrowings' => $borrowings,
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $campuses = Campus::with('locations')->get();
        // Hard-restricted to the student's own campus — they already chose
        // it when completing their profile, and the lab in-charge wants
        // students limited to their own campus's equipment and faculty.
        $availableInventory = $this->inventoryService->getAvailableEquipment($user->campus_id);
        $faculties = User::where('role', 'faculty')->where('status', 'active')->where('campus_id', $user->campus_id)->get();
        $activities = Activity::with('instructor')
            ->whereHas('students', fn($q) => $q->where('activity_students.student_id', auth()->id()))
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->orderBy('activity_date')
            ->get();

        $preselectedInventory = null;
        if ($request->inventory_id) {
            $preselectedInventory = EquipmentInventory::with(['equipment', 'campus', 'location'])
                ->where('available_quantity', '>', 0)
                ->find($request->inventory_id);
        }

        $activeBorrowingCount = $this->borrowingService->activeBorrowingCount($user->id);

        return Inertia::render('Student/Borrowings/Create', [
            'campuses' => $campuses,
            'availableInventory' => $availableInventory,
            'faculties' => $faculties,
            'activities' => $activities,
            'preselectedInventory' => $preselectedInventory,
            'preselectedActivityId' => $request->activity_id ? (int) $request->activity_id : null,
            'activeBorrowingCount' => $activeBorrowingCount,
            'maxActiveBorrowings' => BorrowingService::MAX_ACTIVE_BORROWINGS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'borrow_date' => 'required|date|after_or_equal:today',
            // 'after_or_equal', not 'after' — same-day borrow-and-return is a
            // normal case for lab equipment (borrow for one class session,
            // return by end of the same day), not an edge case to reject.
            'return_date' => 'required|date|after_or_equal:borrow_date',
            'purpose' => 'required|string|max:500',
            'subject' => 'required|string|max:255',
            'faculty_id' => 'required|exists:users,id',
            'activity_id' => 'nullable|exists:activities,id',
            'equipment' => 'required|array|min:1',
            'equipment.*.equipment_id' => 'required|exists:equipment,id',
            'equipment.*.inventory_id' => 'required|exists:equipment_inventory,id',
            'equipment.*.quantity' => 'required|integer|min:1',
        ]);

        // Verify availability
        foreach ($validated['equipment'] as $item) {
            if (!$this->inventoryService->checkAvailability(
                $item['equipment_id'],
                $item['inventory_id'],
                $item['quantity']
            )) {
                return back()->withErrors(['equipment' => 'Some items are no longer available.']);
            }
        }

        // Enforce per-equipment course restrictions server-side. Previously this
        // was only applied to the Browse Equipment listing query, so it could be
        // skipped via a QR scan or a direct request carrying a restricted
        // inventory_id.
        $studentCourse = auth()->user()->course ?? auth()->user()->department ?? null;
        $requestedEquipment = Equipment::whereIn('id', collect($validated['equipment'])->pluck('equipment_id'))
            ->get()
            ->keyBy('id');
        foreach ($validated['equipment'] as $item) {
            $equipment = $requestedEquipment[$item['equipment_id']];
            $restriction = $equipment->course_restriction;
            $allowed = !$restriction || ($studentCourse && str_contains($restriction, $studentCourse));
            if (!$allowed) {
                return back()->withErrors(['equipment' => "{$equipment->name} is restricted to specific courses."]);
            }
        }

        // QR-originated requests: the instructor already pre-approved this item for this
        // activity by generating and sharing its QR code, so skip the faculty approval step
        // (mirrors the shortcut faculty get for their own borrow requests). Resolve this
        // BEFORE creating the borrowing so we can suppress the "requires your approval"
        // email to faculty — it would be inaccurate since this gets auto-approved instantly.
        $qr = null;
        $activity = null;
        if (!empty($validated['activity_id'])) {
            $inventoryIds = collect($validated['equipment'])->pluck('inventory_id')->all();
            $qr = ActivityEquipmentQr::where('activity_id', $validated['activity_id'])
                ->whereIn('equipment_inventory_id', $inventoryIds)
                ->first();
            if ($qr) {
                $activity = Activity::find($validated['activity_id']);
            }
        }

        try {
            $borrowing = $this->borrowingService->createBorrowing(
                $validated,
                $validated['equipment'],
                auth()->id(),
                notifyFaculty: !$qr
            );
        } catch (\App\Exceptions\InsufficientInventoryException $e) {
            return back()->withErrors(['equipment' => $e->getMessage()]);
        } catch (\App\Exceptions\DuplicateSubmissionException $e) {
            return back()->withErrors(['equipment' => $e->getMessage()]);
        } catch (\App\Exceptions\BorrowingLimitExceededException $e) {
            return back()->withErrors(['equipment' => $e->getMessage()]);
        }

        if ($qr && $activity) {
            $borrowing->update([
                'status' => 'pending_admin',
                'faculty_approved_by' => $activity->instructor_id,
                'faculty_approved_at' => now(),
            ]);

            AuditLog::log(auth()->id(), 'Borrow Request Auto-approved via Activity QR', 'borrowings', $borrowing->id, null, [
                'activity_id' => $activity->id,
                'qr_token' => $qr->token,
            ]);

            $borrowing->load('borrowingEquipment.equipment', 'faculty');
            NotificationService::borrowingAutoApprovedViaQr($borrowing, $activity);
        }

        return redirect()->route('student.borrowings.index')
            ->with('success', 'Borrowing request submitted. Please wait for approval.');
    }

    public function show(Borrowing $borrowing)
    {
        if ($borrowing->user_id !== auth()->id()) {
            abort(403);
        }

        $borrowing->load([
            'borrowingEquipment.equipment',
            'borrowingEquipment.inventory.campus',
            'borrowingEquipment.inventory.location',
            'approver',
            'faculty',
            'damageReports',
        ]);

        return Inertia::render('Student/Borrowings/Show', [
            'borrowing' => $borrowing,
        ]);
    }

    public function cancel(Borrowing $borrowing)
    {
        if ($borrowing->user_id !== auth()->id()) {
            return redirect()->route('student.borrowings.index')->with('error', 'You do not have access to this request.');
        }

        if ($borrowing->status !== 'pending_faculty') {
            return back()->with('error', 'Only pending requests can be cancelled.');
        }

        try {
            $borrowing = $this->borrowingService->cancelBorrowing($borrowing, auth()->id(), 'Cancelled Borrow Request (Student)');
        } catch (\App\Exceptions\InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        NotificationService::borrowingCancelled($borrowing);

        return back()->with('success', 'Borrowing request cancelled.');
    }

    public function reportDamage(Request $request, Borrowing $borrowing)
    {
        if ($borrowing->user_id !== auth()->id()) {
            abort(403);
        }

        if (!in_array($borrowing->status, ['borrowed', 'overdue'])) {
            return back()->with('error', 'Cannot report damage for this borrowing.');
        }

        $validated = $request->validate([
            'equipment_id' => 'required|exists:equipment,id',
            'quantity' => 'required|integer|min:1',
            'description' => 'required|string|max:1000',
        ]);

        // The equipment must actually be part of THIS borrowing — without
        // this check, any exists:equipment,id value would be accepted,
        // letting a report be filed against equipment never borrowed here.
        $item = $borrowing->borrowingEquipment()->where('equipment_id', $validated['equipment_id'])->first();
        if (!$item) {
            return back()->withErrors(['equipment_id' => 'That equipment is not part of this borrowing.']);
        }

        // Cap at what's actually left to report: borrowed quantity minus
        // whatever has already been reported damaged for this same item on
        // this same borrowing, so repeated/duplicate reports can't
        // cumulatively claim more damaged units than were ever borrowed.
        $alreadyReported = (int) DamageReport::where('borrowing_id', $borrowing->id)
            ->where('equipment_id', $validated['equipment_id'])
            ->sum('quantity');
        $remaining = $item->quantity - $alreadyReported;
        if ($validated['quantity'] > $remaining) {
            return back()->withErrors(['quantity' => "You can report at most {$remaining} more damaged unit(s) for this item."]);
        }

        DamageReport::create([
            'borrowing_id' => $borrowing->id,
            'equipment_id' => $validated['equipment_id'],
            'quantity' => $validated['quantity'],
            'equipment_inventory_id' => $item->inventory_id,
            'reported_by' => auth()->id(),
            'description' => $validated['description'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Damage report submitted.');
    }

    public function equipment(Request $request)
    {
        $user = auth()->user();
        $studentCourse = $user->course ?? $user->department ?? null;

        $query = EquipmentInventory::with(['equipment', 'campus', 'location'])
            ->whereHas('equipment', function ($q) use ($studentCourse) {
                // Show equipment with no course restriction OR matching the student's course
                $q->where(function ($inner) use ($studentCourse) {
                    $inner->whereNull('course_restriction')
                          ->orWhere('course_restriction', '');
                    if ($studentCourse) {
                        // A student's course/department is free text, so it can contain
                        // LIKE wildcard characters (e.g. a literal "%") — without escaping
                        // them, setting course to "%" would match every restriction and
                        // reveal equipment meant for other programs. Escaping % and _
                        // (and the escape character itself) makes them literal again.
                        $escapedCourse = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $studentCourse);
                        $inner->orWhereRaw("course_restriction LIKE ? ESCAPE '\\'", ["%{$escapedCourse}%"]);
                    }
                });
            });

        // Hard-restricted to the student's own campus (not just a default) —
        // the lab in-charge wants students limited to their own campus's
        // equipment, same as the faculty picker on the borrow request form.
        $query->where('campus_id', $user->campus_id);

        if ($request->category) {
            $query->whereHas('equipment', function ($q) use ($request) {
                $q->where('category', $request->category);
            });
        }

        if ($request->search) {
            $query->whereHas('equipment', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }

        $inventory = $query->paginate(15);
        $categories = Equipment::distinct()->pluck('category')->filter();

        return Inertia::render('Student/Equipment/Index', [
            'inventory' => $inventory,
            'campus' => $user->campus,
            'categories' => $categories,
            'filters' => $request->only(['category', 'search']),
            'studentCourse' => $studentCourse,
        ]);
    }

    public function history()
    {
        // The full activity log for this student's own account — not just
        // returned items, every step (submitted, approved, rejected,
        // released, returned) that the existing AuditLog system already
        // records throughout the borrowing lifecycle.
        $logs = AuditLog::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Student/History', [
            'logs' => $logs,
        ]);
    }
}
