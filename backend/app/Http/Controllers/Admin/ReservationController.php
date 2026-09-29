<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Reservation;
use App\Models\Equipment;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReservationController extends Controller
{
    public function index()
    {
        $reservations = Reservation::with(['user', 'equipment', 'approver'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Reservations/Index', [
            'reservations' => $reservations,
        ]);
    }

    public function show(Reservation $reservation)
    {
        $reservation->load(['user', 'equipment', 'approver']);

        return Inertia::render('Admin/Reservations/Show', [
            'reservation' => $reservation,
        ]);
    }

    public function approve(Reservation $reservation)
    {
        $reservation->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
        ]);

        AuditLog::log(auth()->id(), 'Approved Reservation', 'reservations', $reservation->id);

        // Send notification to user
        UserNotification::create([
            'user_id' => $reservation->user_id,
            'title' => 'Reservation Approved',
            'message' => "Your reservation for {$reservation->equipment->name} has been approved.",
            'type' => 'success',
        ]);

        return redirect()->back()->with('success', 'Reservation approved successfully.');
    }

    public function reject(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $reservation->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'remarks' => $validated['remarks'],
        ]);

        AuditLog::log(auth()->id(), 'Rejected Reservation', 'reservations', $reservation->id, null, ['remarks' => $validated['remarks']]);

        // Send notification to user
        UserNotification::create([
            'user_id' => $reservation->user_id,
            'title' => 'Reservation Rejected',
            'message' => "Your reservation for {$reservation->equipment->name} has been rejected. Reason: {$validated['remarks']}",
            'type' => 'danger',
        ]);

        return redirect()->back()->with('success', 'Reservation rejected.');
    }

    public function destroy(Reservation $reservation)
    {
        AuditLog::log(auth()->id(), 'Deleted Reservation', 'reservations', $reservation->id);

        $reservation->delete();

        return redirect()->route('admin.reservations.index')
            ->with('success', 'Reservation deleted successfully.');
    }
}
