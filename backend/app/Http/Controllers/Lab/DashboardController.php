<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use App\Models\DamageReport;
use App\Models\EquipmentInventory;
use App\Services\FineCalculationService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private FineCalculationService $fineService)
    {
    }

    public function index()
    {
        // Once a day: remind about maintenance scheduled or due today.
        app(\App\Services\MaintenanceReminderService::class)->sendDueTodayOncePerDay();

        $this->fineService->updateAllOverdueBorrowings();

        $user = auth()->user();

        $campusId = $user->campus_id;

        $equipmentQuery = EquipmentInventory::where('campus_id', $campusId);

        $stats = [
            'total_equipment' => (clone $equipmentQuery)->sum('total_quantity'),
            'available' => (clone $equipmentQuery)->sum('available_quantity'),
        ];

        $borrowingsQuery = Borrowing::whereHas('borrowingEquipment.inventory', function ($q) use ($campusId) {
            $q->where('campus_id', $campusId);
        });

        $stats['borrowed'] = (clone $borrowingsQuery)->where('status', 'borrowed')->count();
        $stats['overdue'] = (clone $borrowingsQuery)->where('status', 'overdue')->count();

        $damageQuery = DamageReport::whereHas('borrowing.borrowingEquipment.inventory', function ($q) use ($campusId) {
            $q->where('campus_id', $campusId);
        });

        $stats['damaged'] = (clone $damageQuery)->whereIn('status', ['pending', 'assessed'])->count();

        return Inertia::render('Lab/Dashboard', [
            'stats' => $stats,
        ]);
    }
}

