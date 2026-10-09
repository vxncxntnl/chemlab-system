<?php

namespace App\Http\Controllers\Lab;

use App\Exceptions\DuplicateSubmissionException;
use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InvalidBorrowingStateException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\Campus;
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

    public function create(Request $request)
    {
        $campusId = auth()->user()->campus_id;
        $campuses = Campus::with('locations')->get();
        // Lab staff can only borrow from their own campus
        $availableInventory = $this->inventoryService->getAvailableEquipment($campusId);

        $preselectedInventory = null;
        if ($request->inventory_id) {
            $preselectedInventory = \App\Models\EquipmentInventory::with(['equipment', 'campus', 'location'])
                ->where('campus_id', $campusId)
                ->find($request->inventory_id);
        }

        return Inertia::render('Lab/Borrowings/Create', [
            'campuses' => $campuses,
            'availableInventory' => $availableInventory,
            'preselectedInventory' => $preselectedInventory,
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
            // Only stock at this lab staff member's own campus — never trust
            // an inventory ID from the form for another campus.
            'equipment.*.inventory_id' => ['required', \Illuminate\Validation\Rule::exists('equipment_inventory', 'id')->where('campus_id', auth()->user()->campus_id)],
            'equipment.*.quantity' => 'required|integer|min:1',
        ], [
            'equipment.*.inventory_id.exists' => 'You can only borrow equipment stocked at your own campus.',
        ]);

        // The check here is only a fast, friendly first pass — the real
        // guard is the locked re-check inside createLabInitiatedBorrowing().
        foreach ($validated['equipment'] as $item) {
            if (!$this->inventoryService->checkAvailability(
                $item['equipment_id'],
                $item['inventory_id'],
                $item['quantity']
            )) {
                return back()->withErrors(['equipment' => 'Some items are no longer available in the requested quantity.']);
            }
        }

        // Lab staff borrow requests skip pending_faculty and go directly to
        // pending_admin, since lab staff cannot approve their own requests.
        try {
            $this->borrowingService->createLabInitiatedBorrowing($validated, $validated['equipment'], auth()->id());
        } catch (InsufficientInventoryException|DuplicateSubmissionException $e) {
            return back()->withErrors(['equipment' => $e->getMessage()]);
        }

        return redirect()->route('lab.borrowings.index')
            ->with('success', 'Borrowing request submitted and forwarded to admin for approval.');
    }

    public function index(Request $request)
    {
        $campusId = auth()->user()->campus_id;

        $query = Borrowing::with(['user', 'approver', 'faculty', 'equipment'])
            // Flags requests with an item the student reported as possibly
            // damaged that the staff haven't checked yet.
            ->withExists(['borrowingEquipment as has_damage_report' => fn ($q) => $q->where('damage_report_status', 'reported')])
            ->whereHas('borrowingEquipment.inventory', function ($q) use ($campusId) {
                $q->where('campus_id', $campusId);
            });

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }

        $borrowings = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Lab/Borrowings/Index', [
            'borrowings' => $borrowings,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    /**
     * Lab staff may only act on borrowings whose equipment is stocked at
     * their own campus. index()/show() already enforced this for reads;
     * release()/return() are the state-changing actions and must enforce it
     * too, or a lab staff account at any campus could release/return
     * equipment — and mutate inventory counts — for a different campus.
     */
    private function abortUnlessOwnCampus(Borrowing $borrowing): void
    {
        $campusId = auth()->user()->campus_id;

        if (!$borrowing->borrowingEquipment()->whereHas('inventory', function ($q) use ($campusId) {
            $q->where('campus_id', $campusId);
        })->exists()) {
            abort(403);
        }
    }

    public function show(Borrowing $borrowing)
    {
        $this->abortUnlessOwnCampus($borrowing);

        $borrowing->load([
            'user',
            'faculty',
            'approver',
            'borrowingEquipment.equipment',
            'borrowingEquipment.inventory.campus',
            'borrowingEquipment.inventory.location',
            'damageReports',
        ]);

        return Inertia::render('Lab/Borrowings/Show', [
            'borrowing' => $borrowing,
        ]);
    }

    public function release(Request $request, Borrowing $borrowing)
    {
        $this->abortUnlessOwnCampus($borrowing);

        if ($borrowing->status !== 'approved') {
            return back()->with('error', 'This borrowing is not ready for release.');
        }

        $request->validate([
            'claim_code' => 'required|string',
            'student_id' => 'required|string',
        ]);

        $borrowing->load('user');

        if ($borrowing->claim_code !== $request->input('claim_code')) {
            return back()->with('error', 'Invalid claim code provided.');
        }

        $expectedId = (string) ($borrowing->user->student_id ?? $borrowing->user->employee_id ?? '');
        if ($expectedId === '' || $expectedId !== (string) $request->input('student_id')) {
            return back()->with('error', 'Provided ID does not match this borrowing record.');
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
     * Record a verified damage report for one borrowed item (usually after
     * the student reported possible damage and brought it in), with an
     * optional photo as evidence.
     */
    public function recordDamage(Request $request, Borrowing $borrowing)
    {
        $this->abortUnlessOwnCampus($borrowing);

        $validated = $request->validate([
            'borrowing_equipment_id' => 'required|integer',
            'quantity'               => 'required|integer|min:1',
            'description'            => 'required|string|max:1000',
            // Checked by real file content; up to 10 MB (phone photos).
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
        $this->abortUnlessOwnCampus($borrowing);
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
        $this->abortUnlessOwnCampus($borrowing);

        if (!in_array($borrowing->status, ['borrowed', 'overdue'])) {
            return back()->with('error', 'This borrowing cannot be returned.');
        }

        $request->validate([
            'conditions'           => 'required|array',
            'conditions.*.good'    => 'required|integer|min:0',
            'conditions.*.damaged' => 'required|integer|min:0',
            'remarks'              => 'nullable|string|max:1000',
            'liable_party'         => 'nullable|in:student,faculty,shared',
            'liable_reason'        => 'nullable|string|max:1000',
        ]);

        $conditions = $request->input('conditions', []);
        try {
            $this->borrowingService->returnBorrowing(
                $borrowing,
                $conditions,
                auth()->id(),
                $request->input('liable_party'),
                $request->input('liable_reason')
            );
        } catch (InvalidBorrowingStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($request->filled('remarks')) {
            $borrowing->update(['remarks' => $request->input('remarks')]);
        }

        NotificationService::borrowingReturned($borrowing);

        return back()->with('success', 'Equipment returned successfully.');
    }

    public function history()
    {
        // The full activity log for this lab staff member's own account —
        // equipment releases, returns, damage report assessments, inventory
        // changes, etc. — using the existing AuditLog system rather than a
        // separate query.
        $logs = AuditLog::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Lab/History', [
            'logs' => $logs,
        ]);
    }
}

