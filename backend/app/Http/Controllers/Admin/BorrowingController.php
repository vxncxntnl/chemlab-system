<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidBorrowingStateException;
use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use App\Services\BorrowingService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BorrowingController extends Controller
{
    public function __construct(
        private BorrowingService $borrowingService
    ) {
    }

    public function index(Request $request)
    {
        $query = Borrowing::with(['user', 'approver', 'equipment']);

        if ($request->status) {
            $query->where('status', $request->status);
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
        if ($borrowing->status !== 'pending_admin') {
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
        if ($borrowing->status !== 'pending_admin') {
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

