<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\FineCalculationService;
use App\Support\CampusAccess;
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
        // Once a day: remind about maintenance scheduled or due today.
        app(\App\Services\MaintenanceReminderService::class)->sendDueTodayOncePerDay();

        // Keep overdue statuses up to date for dashboard counters.
        $this->fineService->updateAllOverdueBorrowings();

        // Everything on the dashboard is this admin's campus only.
        $admin = auth()->user();
        $campusId = CampusAccess::adminCampusId($admin);
        $borrowings = fn () => CampusAccess::borrowings(Borrowing::query(), $campusId);

        $stats = [
            'total_users' => CampusAccess::users(User::query(), $admin)->count(),
            'total_equipment' => CampusAccess::equipment(Equipment::query(), $campusId)->count(),
            'pending_requests' => $borrowings()->whereIn('status', ['pending_faculty', 'pending_admin'])->count(),
            'active_borrowings' => $borrowings()->whereIn('status', ['approved', 'borrowed'])->count(),
            'overdue_borrowings' => $borrowings()->where('status', 'overdue')->count(),
            'pending_damage_reports' => CampusAccess::damageReports(DamageReport::query(), $campusId)->where('status', 'pending')->count(),
        ];

        $inventoryStats = $this->inventoryService->getInventoryStats($campusId);

        $recentBorrowings = $borrowings()->with(['user', 'equipment'])
            ->latest()
            ->take(5)
            ->get();

        $lowStockItems = $this->inventoryService->getLowStockItems($campusId)->take(5);

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'inventoryStats' => $inventoryStats,
            'recentBorrowings' => $recentBorrowings,
            'lowStockItems' => $lowStockItems,
        ]);
    }
}
