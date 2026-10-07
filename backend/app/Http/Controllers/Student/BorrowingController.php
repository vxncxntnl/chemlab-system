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
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
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

        // Came from an activity's QR code (and joined it): pre-fill the form
        // with ALL of the activity's equipment, unless an old per-item QR
        // pre-selected a single item.
        $preselectedActivity = $request->activity_id ? $activities->firstWhere('id', (int) $request->activity_id) : null;
        $activityInventoryIds = $preselectedActivity && !$preselectedInventory
            ? $preselectedActivity->selectedInventoryIds()
            : [];

        return Inertia::render('Student/Borrowings/Create', [
            'campuses' => $campuses,
            'availableInventory' => $availableInventory,
            'faculties' => $faculties,
            'activities' => $activities,
            'preselectedInventory' => $preselectedInventory,
            'preselectedActivityId' => $request->activity_id ? (int) $request->activity_id : null,
            'activityInventoryIds' => $activityInventoryIds,
            'activeBorrowingCount' => $activeBorrowingCount,
            'maxActiveBorrowings' => BorrowingService::MAX_ACTIVE_BORROWINGS,
        ]);
    }

    public function store(Request $request)
    {
        // A request for an activity the student joined uses the borrow and
        // return dates the faculty set on that activity, regardless of what
        // the form sent. Older activities without a return date keep the
        // student-chosen dates.
        if ($request->filled('activity_id')) {
            $activity = Activity::whereKey($request->activity_id)
                ->whereNotNull('return_date')
                ->whereHas('students', fn ($q) => $q->where('activity_students.student_id', auth()->id()))
                ->first();
            if ($activity) {
                $request->merge([
                    'borrow_date' => $activity->activity_date->toDateString(),
                    'return_date' => $activity->return_date->toDateString(),
                ]);
            }
        }

        $validated = $request->validate([
            'borrow_date' => 'required|date|after_or_equal:today',
            // 'after_or_equal', not 'after' — same-day borrow-and-return is a
            // normal case for lab equipment (borrow for one class session,
            // return by end of the same day), not an edge case to reject.
            'return_date' => 'required|date|after_or_equal:borrow_date',
            'purpose' => 'required|string|max:500',
            'subject' => 'required|string|max:255',
            // Must be an active faculty account — only role:faculty users can
            // approve, so a request routed to anyone else (e.g. a stale form
            // after that user's role changed) could never be approved.
            // Not chosen for an activity request: it's the activity's
            // instructor (set below).
            'faculty_id' => ['exclude_unless:activity_id,null', 'required', Rule::exists('users', 'id')->where('role', 'faculty')->where('status', 'active')->whereNull('deleted_at')],
            'activity_id' => 'nullable|exists:activities,id',
            'equipment' => 'required|array|min:1',
            'equipment.*.equipment_id' => 'required|exists:equipment,id',
            'equipment.*.inventory_id' => 'required|exists:equipment_inventory,id',
            'equipment.*.quantity' => 'required|integer|min:1',
        ], [
            'faculty_id.exists' => 'The selected faculty member is not available. Please refresh the page and choose another faculty member.',
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
            // A form opened before the activity was cancelled can't still
            // borrow (and get auto-approved) for it.
            $linked = Activity::find($validated['activity_id']);
            if ($linked && ($linked->status === 'cancelled' || $linked->isExpired())) {
                return back()->withErrors(['activity_id' => "The activity \"{$linked->title}\" is no longer active, so you can't borrow for it."]);
            }
            // Same course rule as the QR scan, enforced here too so it can't
            // be bypassed by submitting an activity_id directly.
            if ($linked && !$linked->allowsCourse(auth()->user()->course)) {
                return back()->withErrors(['activity_id' => "The activity \"{$linked->title}\" is only for {$linked->department} students."]);
            }

            // Only students who joined the activity (by scanning its QR) can
            // borrow for it.
            if ($linked && !$linked->students()->where('activity_students.student_id', auth()->id())->exists()) {
                return back()->withErrors(['activity_id' => "Scan the QR code for \"{$linked->title}\" from your instructor to borrow for this activity."]);
            }

            if ($linked) {
                // The faculty in-charge is always the activity's instructor,
                // never a value from the form.
                $validated['faculty_id'] = $linked->instructor_id;

                // The equipment list is the instructor's: a request for the
                // activity can only include equipment they selected for it
                // (students may change quantities or leave items out).
                $selected = $linked->selectedInventoryIds();
                $requested = collect($validated['equipment'])->pluck('inventory_id')->map(fn ($id) => (int) $id);
                if ($selected && $requested->diff($selected)->isNotEmpty()) {
                    return back()->withErrors(['equipment' => "A request for \"{$linked->title}\" can only include the equipment your instructor selected for it."]);
                }

                // One active request per student per activity, so scanning the
                // QR again (or a double submit) can't create duplicates.
                $existing = Borrowing::where('user_id', auth()->id())
                    ->where('activity_id', $linked->id)
                    ->whereIn('status', ['pending_faculty', 'pending_admin', 'approved', 'borrowed', 'overdue'])
                    ->first();
                if ($existing) {
                    return back()->withErrors(['activity_id' => "You already have a request for \"{$linked->title}\" (#{$existing->id}). You can view it in My Borrowings."]);
                }

                // The instructor pre-approved this equipment by selecting it
                // for the activity, so a request made entirely of it skips
                // the faculty step.
                if ($selected && $requested->diff($selected)->isEmpty()) {
                    $qr = true;
                    $activity = $linked;
                }
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

    /**
     * One tap, no form or photo: tells the lab staff that a specific
     * borrowed item may be damaged. The student then brings it to the lab,
     * where staff verify it and record the actual damage report.
     */
    public function reportDamage(Request $request, Borrowing $borrowing)
    {
        if ($borrowing->user_id !== auth()->id()) {
            abort(403);
        }

        if (!in_array($borrowing->status, ['borrowed', 'overdue'], true)) {
            return back()->with('error', 'You can only report damage on equipment you currently have.');
        }

        $validated = $request->validate([
            'borrowing_equipment_id' => 'required|integer',
            // Optional one-liner (e.g. "cracked rim") so staff know what to check.
            'note'                   => 'nullable|string|max:255',
        ]);
        $item = $borrowing->borrowingEquipment()->with(['equipment', 'inventory.location'])
            ->whereKey($validated['borrowing_equipment_id'])->first();
        if (!$item) {
            return back()->withErrors(['borrowing_equipment_id' => 'That equipment is not part of this borrowing.']);
        }

        // Already waiting for the lab staff, or already recorded: no repeat alert.
        if ($item->damage_report_status === 'reported') {
            return back()->with('info', 'The lab staff were already notified about this item. Please bring it to the laboratory.');
        }
        if ($item->damage_report_status === 'confirmed') {
            return back()->with('info', 'The lab staff have already recorded damage for this item.');
        }

        $item->update([
            'damage_report_status' => 'reported',
            'damage_reported_at'   => now(),
            'damage_report_note'   => $validated['note'] ?? null,
        ]);

        AuditLog::log(auth()->id(), 'Reported Possible Damage (Student)', 'borrowings', $borrowing->id, null, [
            'borrowing_equipment_id' => $item->id,
            'equipment_id' => $item->equipment_id,
            'note' => $validated['note'] ?? null,
        ]);
        NotificationService::possibleDamageReported($borrowing, $item, auth()->user());

        return back()->with('success', "The lab staff have been notified that {$item->equipment?->name} may be damaged. Please bring it to the laboratory so they can check it.");
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
