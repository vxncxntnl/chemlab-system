<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\FineCalculationService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private FineCalculationService $fineService
    )
    {
    }

    public function index()
    {
        // Keep overdue statuses up to date for dashboard counters.
        $this->fineService->updateAllOverdueBorrowings();

        $stats = [
            'total_users' => User::count(),
            'total_equipment' => Equipment::count(),
            'pending_requests' => Borrowing::whereIn('status', ['pending_faculty', 'pending_admin'])->count(),
            'active_borrowings' => Borrowing::whereIn('status', ['approved', 'borrowed'])->count(),
            'overdue_borrowings' => Borrowing::where('status', 'overdue')->count(),
            'pending_damage_reports' => DamageReport::where('status', 'pending')->count(),
        ];

        $inventoryStats = $this->inventoryService->getInventoryStats();

        $recentBorrowings = Borrowing::with(['user', 'equipment'])
            ->latest()
            ->take(5)
            ->get();

        $lowStockItems = $this->inventoryService->getLowStockItems()->take(5);

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'inventoryStats' => $inventoryStats,
            'recentBorrowings' => $recentBorrowings,
            'lowStockItems' => $lowStockItems,
        ]);
    }
}
