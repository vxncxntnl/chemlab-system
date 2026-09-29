<?php

namespace App\Http\Controllers\Faculty;

use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use App\Services\FineCalculationService;
use App\Models\Equipment;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private FineCalculationService $fineService)
    {
    }

    public function index()
    {
        $user = auth()->user();

        $this->fineService->updateAllOverdueBorrowings();

        $stats = [
            'active_borrowings' => Borrowing::where('user_id', $user->id)
                ->whereIn('status', ['pending_faculty', 'pending_admin', 'approved', 'borrowed'])
                ->count(),
            'pending_requests' => Borrowing::where('user_id', $user->id)
                ->whereIn('status', ['pending_faculty', 'pending_admin'])
                ->count(),
            'overdue_items' => Borrowing::where('user_id', $user->id)
                ->where('status', 'overdue')
                ->count(),
        ];

        $recentBorrowings = Borrowing::with('equipment')
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Faculty/Dashboard', [
            'stats' => $stats,
            'recentBorrowings' => $recentBorrowings,
        ]);
    }
}
