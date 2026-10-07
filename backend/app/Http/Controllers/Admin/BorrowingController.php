<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DuplicateSubmissionException;
use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InvalidBorrowingStateException;
use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use App\Models\Campus;
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
        $query = Borrowing::with(['user', 'approver', 'faculty', 'equipment']);

        if ($request->status) {
            // Accepts either a single status or an array of statuses (e.g.
            // the dashboard's "Pending Requests" card links here with both
            // pending_faculty and pending_admin, matching how it counts them).
            $query->whereIn('status', (array) $request->status);
        }

        if ($request->search) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }

        $borrowings = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Admin/Borrowings/Index', [
            'borrowings' => $borrowings,
            'filters' => $request->only(['status', 'search']),
        ]);
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
        $campuses = Campus::with('locations')->get();
        $availableInventory = $this->inventoryService->getAvailableEquipment();

        $preselectedInventory = null;
        if ($request->inventory_id) {
            $preselectedInventory = EquipmentInventory::with(['equipment', 'campus', 'location'])
                ->where('available_quantity', '>', 0)
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
            'equipment.*.inventory_id' => 'required|exists:equipment_inventory,id',
            'equipment.*.quantity' => 'required|integer|min:1',
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

