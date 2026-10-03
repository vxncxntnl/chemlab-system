<?php

namespace App\Http\Controllers\Faculty;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Services\BorrowingService;
use App\Services\InventoryService;
use App\Services\NotificationService;
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

        $query = Borrowing::with(['equipment', 'approver', 'user', 'faculty'])
            ->where(function ($q) use ($user) {
                $q->where('faculty_id', $user->id);
                // Keep ability to view their own requests if created with faculty_id.
                $q->orWhere('user_id', $user->id);
            });

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $borrowings = $query->orderBy('created_at', 'desc')->paginate(10);

        return Inertia::render('Faculty/Borrowings/Index', [
            'borrowings' => $borrowings,
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $campuses = Campus::with('locations')->get();
        // Hard-restricted to the faculty's own campus — same rule as
        // students, per the lab in-charge's instruction.
        $availableInventory = $this->inventoryService->getAvailableEquipment($user->campus_id);

        $preselectedInventory = null;
        if ($request->inventory_id) {
            $preselectedInventory = \App\Models\EquipmentInventory::with(['equipment', 'campus', 'location'])
                ->where('available_quantity', '>', 0)
                ->find($request->inventory_id);
        }

        $activeBorrowingCount = $this->borrowingService->activeBorrowingCount($user->id);

        return Inertia::render('Faculty/Borrowings/Create', [
            'campuses' => $campuses,
            'availableInventory' => $availableInventory,
            'preselectedInventory' => $preselectedInventory,
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
            'purpose' => 'nullable|string|max:500',
            'equipment' => 'required|array|min:1',
            'equipment.*.equipment_id' => 'required|exists:equipment,id',
            'equipment.*.inventory_id' => 'required|exists:equipment_inventory,id',
            'equipment.*.quantity' => 'required|integer|min:1',
        ]);

        // Verify availability for all items
        foreach ($validated['equipment'] as $item) {
            if (!$this->inventoryService->checkAvailability(
                $item['equipment_id'],
                $item['inventory_id'],
                $item['quantity']
            )) {
                return back()->withErrors(['equipment' => 'Some items are no longer available in the requested quantity.']);
            }
        }

        try {
            $borrowing = $this->borrowingService->createBorrowing(
                $validated,
                $validated['equipment'],
                auth()->id()
            );
        } catch (\App\Exceptions\InsufficientInventoryException $e) {
            return back()->withErrors(['equipment' => $e->getMessage()]);
        } catch (\App\Exceptions\DuplicateSubmissionException $e) {
            return back()->withErrors(['equipment' => $e->getMessage()]);
        } catch (\App\Exceptions\BorrowingLimitExceededException $e) {
            return back()->withErrors(['equipment' => $e->getMessage()]);
        }

        // Faculty creates their own request — skip faculty approval stage
        $borrowing->update([
            'status' => 'pending_admin',
            'faculty_approved_by' => auth()->id(),
            'faculty_approved_at' => now(),
        ]);

        return redirect()->route('faculty.borrowings.index')
            ->with('success', 'Borrowing request submitted successfully.');
    }

    public function show(Borrowing $borrowing)
    {
        if ($borrowing->user_id !== auth()->id() && $borrowing->faculty_id !== auth()->id()) {
            return redirect()->route('faculty.borrowings.index')->with('error', 'You do not have access to this request.');
        }

        $borrowing->load([
            'user',
            'faculty',
            'borrowingEquipment.equipment',
            'borrowingEquipment.inventory.campus',
            'borrowingEquipment.inventory.location',
            'approver',
            'damageReports',
        ]);

        return Inertia::render('Faculty/Borrowings/Show', [
            'borrowing' => $borrowing,
        ]);
    }

    public function cancel(Borrowing $borrowing)
    {
        if ($borrowing->user_id !== auth()->id() && $borrowing->faculty_id !== auth()->id()) {
            return redirect()->route('faculty.borrowings.index')->with('error', 'You do not have access to this request.');
        }

        if ($borrowing->status !== 'pending_faculty') {
            return back()->with('error', 'This request cannot be cancelled.');
        }

        try {
            $this->borrowingService->cancelBorrowing($borrowing, auth()->id(), 'Cancelled Borrow Request (Faculty)');
        } catch (\App\Exceptions\InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Borrowing request cancelled.');
    }

    public function approve(Borrowing $borrowing)
    {
        if ($borrowing->faculty_id !== auth()->id()) {
            return back()->with('error', 'You are not the assigned faculty for this request.');
        }

        if ($borrowing->status !== 'pending_faculty') {
            return back()->with('error', 'This request is not waiting for faculty approval.');
        }

        try {
            $borrowing = $this->borrowingService->facultyApproveBorrowing($borrowing, auth()->id());
        } catch (\App\Exceptions\InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        NotificationService::borrowingApprovedByFaculty($borrowing, auth()->user());

        return back()->with('success', 'Borrowing request approved by faculty.');
    }

    public function reject(Request $request, Borrowing $borrowing)
    {
        if ($borrowing->faculty_id !== auth()->id()) {
            return back()->with('error', 'You are not the assigned faculty for this request.');
        }

        if ($borrowing->status !== 'pending_faculty') {
            return back()->with('error', 'This request is not waiting for faculty approval.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $borrowing = $this->borrowingService->facultyRejectBorrowing($borrowing, auth()->id(), $validated['reason'] ?? null);
        } catch (\App\Exceptions\InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        NotificationService::borrowingRejectedByFaculty($borrowing, auth()->user());

        return back()->with('success', 'Borrowing request rejected by faculty.');
    }

    public function reportDamage(Request $request, Borrowing $borrowing)
    {
        if ($borrowing->user_id !== auth()->id()) {
            return back()->with('error', 'You can only report damage on your own borrowing requests.');
        }

        if (!in_array($borrowing->status, ['borrowed', 'overdue'])) {
            return back()->with('error', 'Cannot report damage for this borrowing.');
        }

        $validated = $request->validate([
            'equipment_id' => 'required|exists:equipment,id',
            'quantity' => 'required|integer|min:1',
            'description' => 'required|string|max:1000',
        ]);

        $item = $borrowing->borrowingEquipment()->where('equipment_id', $validated['equipment_id'])->first();
        if (!$item) {
            return back()->withErrors(['equipment_id' => 'That equipment is not part of this borrowing.']);
        }

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
        $query = EquipmentInventory::with(['equipment', 'campus', 'location']);

        // Hard-restricted to the faculty's own campus (not just a default) —
        // same rule as students, per the lab in-charge's instruction.
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

        return Inertia::render('Faculty/Equipment/Index', [
            'inventory' => $inventory,
            'campus' => $user->campus,
            'categories' => $categories,
            'filters' => $request->only(['category', 'search']),
        ]);
    }

    public function history()
    {
        // The full activity log for this faculty's own account — both their
        // own borrow requests and their approve/reject actions on others',
        // using the existing AuditLog system rather than a separate query.
        $logs = AuditLog::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Faculty/History', [
            'logs' => $logs,
        ]);
    }
}
