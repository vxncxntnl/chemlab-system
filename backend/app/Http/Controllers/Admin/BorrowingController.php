<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DuplicateSubmissionException;
use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InvalidBorrowingStateException;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\EquipmentInventory;
use App\Models\UserNotification;
use App\Services\BorrowingService;
use App\Services\InventoryService;
use App\Services\NotificationService;
use App\Support\CampusAccess;
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
        // The list's filters, applied to walk-in requests and to the
        // requests inside each QR activity group alike.
        // Only borrowings of this admin's campus.
        $campusId = CampusAccess::adminCampusId(auth()->user());
        $filters = function ($q) use ($request, $campusId) {
            CampusAccess::borrowings($q, $campusId);
            if ($request->status) {
                // Accepts either a single status or an array of statuses (e.g.
                // the dashboard's "Pending Requests" card links here with both
                // pending_faculty and pending_admin, matching how it counts them).
                $q->whereIn('status', (array) $request->status);
            }
            if ($request->search) {
                $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$request->search}%"));
            }
        };

        // QR activity requests are shown as ONE row per activity (its latest
        // matching request stands for the group); walk-in/direct requests
        // stay one row each. Each student's request is still its own
        // borrowing — this only changes how the list displays them.
        $latestPerActivity = Borrowing::query()->whereNotNull('activity_id')->where($filters)
            ->selectRaw('MAX(id)')->groupBy('activity_id');

        $borrowings = Borrowing::with(['user', 'approver', 'faculty', 'equipment', 'activity:id,title,instructor_id', 'activity.instructor:id,name'])
            // Flags requests with an item the student reported as possibly
            // damaged that the staff haven't checked yet.
            ->withExists(['borrowingEquipment as has_damage_report' => fn ($q) => $q->where('damage_report_status', 'reported')])
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNull('activity_id')->where($filters))
                ->orWhereIn('id', $latestPerActivity))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $groups = $this->activityGroupSummaries($borrowings->getCollection()->pluck('activity_id')->filter()->unique()->all());
        $borrowings->getCollection()->each(function (Borrowing $row) use ($groups) {
            $row->setAttribute('group', $row->activity_id ? ($groups[$row->activity_id] ?? null) : null);
        });

        return Inertia::render('Admin/Borrowings/Index', [
            'borrowings' => $borrowings,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    /**
     * Per activity: how many students requested, how many wait for the
     * admin, how many are still out, and how many of the admin's "New QR
     * Activity Request" notifications for it are unread.
     */
    private function activityGroupSummaries(array $activityIds): array
    {
        if (!$activityIds) {
            return [];
        }

        $requests = Borrowing::whereIn('activity_id', $activityIds)->get(['id', 'activity_id', 'user_id', 'status']);
        $unread = $this->unreadQrNotifications($requests)->get(['link'])
            ->countBy(fn ($n) => $requests->firstWhere('id', (int) basename($n->link))?->activity_id);

        return $requests->groupBy('activity_id')->map(fn ($rows, $activityId) => [
            'requests' => $rows->count(),
            // Cancelled requests don't count (unless all of them were cancelled).
            'students' => ($rows->where('status', '!=', 'cancelled')->isNotEmpty() ? $rows->where('status', '!=', 'cancelled') : $rows)
                ->pluck('user_id')->unique()->count(),
            'need_approval' => $rows->where('status', 'pending_admin')->count(),
            'in_progress' => $rows->whereIn('status', ['pending_faculty', 'approved', 'borrowed', 'overdue'])->count(),
            'unread' => $unread[$activityId] ?? 0,
        ])->all();
    }

    /**
     * The signed-in admin's unread "New QR Activity Request" notifications
     * for the given requests (the existing notifications, matched by link).
     */
    private function unreadQrNotifications($requests)
    {
        return UserNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->where('title', NotificationService::QR_REQUEST_TITLE)
            ->whereIn('link', $requests->map(fn ($b) => "/admin/borrowings/{$b->id}")->all());
    }

    /**
     * One QR activity's student requests, opened from its grouped row.
     * Opening it marks the admin's notifications for them as read.
     */
    public function activity(Activity $activity)
    {
        $activity->load('instructor:id,name');
        $requests = $activity->borrowings()
            ->with(['user:id,name,email', 'borrowingEquipment.equipment:id,name'])
            ->withExists(['borrowingEquipment as has_damage_report' => fn ($q) => $q->where('damage_report_status', 'reported')])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $this->unreadQrNotifications($requests)->update(['read_at' => now()]);

        return Inertia::render('Admin/Borrowings/Activity', [
            'activity' => $activity->only(['id', 'title', 'department', 'activity_date', 'return_date', 'status']) + [
                'instructor' => $activity->instructor?->name,
            ],
            'requests' => $requests,
            'needApprovalCount' => $requests->where('status', 'pending_admin')->count(),
        ]);
    }

    /**
     * "Approve all" for one activity: approves each of its requests that is
     * waiting for the admin, one by one through the normal approval (same
     * checks, history and notifications as approving them individually).
     * Each student keeps their own request, claim code, release and return.
     */
    public function approveActivity(Activity $activity)
    {
        $pending = $activity->borrowings()->where('status', 'pending_admin')->get();
        $admin = auth()->user();
        $approved = 0;
        $failed = 0;

        foreach ($pending as $borrowing) {
            try {
                $borrowing = $this->borrowingService->approveBorrowing($borrowing, $admin->id);
                // Emails go out right after the page responds, so approving a
                // whole class doesn't keep the admin waiting on 20+ emails.
                defer(fn () => NotificationService::borrowingApprovedByAdmin($borrowing, $admin));
                $approved++;
            } catch (InvalidBorrowingStateException $e) {
                // e.g. another admin approved or cancelled it a moment ago
                $failed++;
            }
        }

        if (!$approved) {
            return back()->with('error', "No requests for \"{$activity->title}\" are waiting for approval.");
        }

        return back()->with('success', "{$approved} request(s) for \"{$activity->title}\" approved."
            . ($failed ? " {$failed} could not be approved (already changed) — check them individually." : ''));
    }

    /**
     * Admin can originate a walk-in borrowing request directly — the same
     * capability Lab Staff has (Lab\BorrowingController::create/store) — as
     * a backup when no lab staff/student assistant is available. Unlike
     * Lab Staff, Admin isn't restricted to one campus, since none of
     * Admin's other pages are campus-scoped either.
     */
    public function create(Request $request)
    {
        // Walk-in requests use this admin's campus stock only.
        $campusId = CampusAccess::adminCampusId(auth()->user());
        $campuses = Campus::with('locations')->whereKey($campusId)->get();
        $availableInventory = $this->inventoryService->getAvailableEquipment($campusId);

        $preselectedInventory = null;
        if ($request->inventory_id) {
            $preselectedInventory = EquipmentInventory::with(['equipment', 'campus', 'location'])
                ->where('available_quantity', '>', 0)
                ->where('campus_id', $campusId)
                ->find($request->inventory_id);
        }

        return Inertia::render('Admin/Borrowings/Create', [
            'campuses' => $campuses,
            'availableInventory' => $availableInventory,
            'preselectedInventory' => $preselectedInventory,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'borrow_date' => 'required|date|after_or_equal:today',
            'return_date' => 'required|date|after_or_equal:borrow_date',
            'purpose' => 'nullable|string|max:500',
            'equipment' => 'required|array|min:1',
            'equipment.*.equipment_id' => 'required|exists:equipment,id',
            'equipment.*.inventory_id' => ['required', \Illuminate\Validation\Rule::exists('equipment_inventory', 'id')->where('campus_id', CampusAccess::adminCampusId(auth()->user()))],
            'equipment.*.quantity' => 'required|integer|min:1',
        ], [
            'equipment.*.inventory_id.exists' => 'You can only use equipment stocked at your own campus.',
        ]);

        foreach ($validated['equipment'] as $item) {
            if (!$this->inventoryService->checkAvailability(
                $item['equipment_id'],
                $item['inventory_id'],
                $item['quantity']
            )) {
                return back()->withErrors(['equipment' => 'Some items are no longer available in the requested quantity.']);
            }
        }

        // Reuses the same lab-initiated flow (skips pending_faculty, lands
        // straight at pending_admin) — correct here too, since an admin
        // originating a walk-in request has no "self-approval" step either.
        try {
            $this->borrowingService->createLabInitiatedBorrowing($validated, $validated['equipment'], auth()->id());
        } catch (InsufficientInventoryException|DuplicateSubmissionException $e) {
            return back()->withErrors(['equipment' => $e->getMessage()]);
        }

        return redirect()->route('admin.borrowings.index')
            ->with('success', 'Borrowing request submitted and is pending approval.');
    }

    public function show(Borrowing $borrowing)
    {
        $borrowing->load([
            'user',
            'faculty',
            'approver',
            'borrowingEquipment.equipment',
            'borrowingEquipment.inventory.campus',
            'borrowingEquipment.inventory.location',
            'damageReports',
            'activity.instructor:id,name',
        ]);

        return Inertia::render('Admin/Borrowings/Show', [
            'borrowing' => $borrowing,
        ]);
    }

    public function approve(Borrowing $borrowing)
    {
        // Faculty approval is optional — admin can approve directly from
        // 'pending_faculty', without waiting for faculty to act first.
        if (!in_array($borrowing->status, ['pending_admin', 'pending_faculty'])) {
            return back()->with('error', 'This borrowing request cannot be approved.');
        }

        try {
            $borrowing = $this->borrowingService->approveBorrowing($borrowing, auth()->id());
        } catch (InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        NotificationService::borrowingApprovedByAdmin($borrowing, auth()->user());

        return back()->with('success', 'Borrowing request approved successfully.');
    }

    public function reject(Request $request, Borrowing $borrowing)
    {
        // Same as approve() — admin can reject directly from
        // 'pending_faculty' too.
        if (!in_array($borrowing->status, ['pending_admin', 'pending_faculty'])) {
            return back()->with('error', 'This borrowing request cannot be rejected.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $borrowing = $this->borrowingService->rejectBorrowing($borrowing, auth()->id(), $validated['reason'] ?? null);
        } catch (InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        NotificationService::borrowingRejectedByAdmin($borrowing, auth()->user());

        return back()->with('success', 'Borrowing request rejected.');
    }

    public function release(Borrowing $borrowing)
    {
        if ($borrowing->status !== 'approved') {
            return back()->with('error', 'This borrowing is not ready for release.');
        }

        try {
            $borrowing = $this->borrowingService->markAsBorrowed($borrowing, auth()->id());
        } catch (InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        NotificationService::borrowingReleased($borrowing, auth()->user());

        return back()->with('success', 'Equipment released to borrower.');
    }

    /**
     * Same as lab staff's: record a verified damage report for one borrowed
     * item, with an optional photo — the admin's backup when no lab staff
     * is available.
     */
    public function recordDamage(Request $request, Borrowing $borrowing)
    {
        $validated = $request->validate([
            'borrowing_equipment_id' => 'required|integer',
            'quantity'               => 'required|integer|min:1',
            'description'            => 'required|string|max:1000',
            'photo'                  => 'nullable|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        try {
            $this->borrowingService->recordDamage(
                $borrowing,
                (int) $validated['borrowing_equipment_id'],
                (int) $validated['quantity'],
                $validated['description'],
                $request->file('photo'),
                auth()->id()
            );
        } catch (InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Damage report recorded.');
    }

    /** The student-reported item was checked and is fine. */
    public function noDamage(Request $request, Borrowing $borrowing)
    {
        $validated = $request->validate(['borrowing_equipment_id' => 'required|integer']);

        try {
            $this->borrowingService->markNoDamage($borrowing, (int) $validated['borrowing_equipment_id'], auth()->user());
        } catch (InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Marked as checked — no damage found. The student has been notified.');
    }

    public function return(Request $request, Borrowing $borrowing)
    {
        if (!in_array($borrowing->status, ['borrowed', 'overdue'])) {
            return back()->with('error', 'This borrowing cannot be returned.');
        }

        // Previously unvalidated — any shape of "conditions" was accepted
        // as-is with no type checking. Admin's own "Mark Returned" button
        // posts no body at all (a simple "everything came back fine" —
        // returnBorrowing() already defaults an absent per-item entry to
        // "all good, none damaged"), so conditions stays optional here
        // rather than required like Lab's more detailed form; when it IS
        // present, it's now actually validated instead of accepted as-is.
        $validated = $request->validate([
            'conditions'            => 'nullable|array',
            'conditions.*.good'     => 'nullable|integer|min:0',
            'conditions.*.damaged'  => 'nullable|integer|min:0',
            'remarks'               => 'nullable|string|max:1000',
            'liable_party'          => 'nullable|in:student,faculty,shared',
            'liable_reason'         => 'nullable|string|max:1000',
        ]);

        try {
            $borrowing = $this->borrowingService->returnBorrowing(
                $borrowing,
                $validated['conditions'] ?? [],
                auth()->id(),
                $validated['liable_party'] ?? null,
                $validated['liable_reason'] ?? null
            );
        } catch (InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($request->filled('remarks')) {
            $borrowing->update(['remarks' => $validated['remarks']]);
        }

        NotificationService::borrowingReturned($borrowing);

        return back()->with('success', 'Equipment returned successfully.');
    }
}

