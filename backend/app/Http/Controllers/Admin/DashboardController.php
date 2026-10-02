<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use App\Services\InventoryService;
use App\Services\FineCalculationService;
use Illuminate\Support\Facades\DB;
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

        // Combines what used to be 6 separate queries (across 4 tables) into
        // a single round trip. users/equipment/borrowings use soft deletes,
        // so those subqueries exclude deleted_at the same way the original
        // ::count() calls already did; damage_reports has no soft deletes.
        $statsRow = (array) DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS total_users,
                (SELECT COUNT(*) FROM equipment WHERE deleted_at IS NULL) AS total_equipment,
                (SELECT COUNT(*) FROM borrowings WHERE status IN ('pending_faculty', 'pending_admin') AND deleted_at IS NULL) AS pending_requests,
                (SELECT COUNT(*) FROM borrowings WHERE status IN ('approved', 'borrowed') AND deleted_at IS NULL) AS active_borrowings,
                (SELECT COUNT(*) FROM borrowings WHERE status = 'overdue' AND deleted_at IS NULL) AS overdue_borrowings,
                (SELECT COUNT(*) FROM damage_reports WHERE status = 'pending') AS pending_damage_reports
        ");

        $stats = array_map('intval', $statsRow);

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
