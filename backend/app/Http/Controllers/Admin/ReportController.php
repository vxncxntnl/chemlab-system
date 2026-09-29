<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Equipment;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(private InventoryService $inventoryService)
    {
    }

    public function lowStock()
    {
        $items = $this->inventoryService->getLowStockItems();

        return Inertia::render('Admin/Reports/LowStock', [
            'items' => $items,
        ]);
    }

    public function mostBorrowed(Request $request)
    {
        $limit = $request->input('limit', 10);
        $equipment = $this->inventoryService->getMostBorrowedEquipment($limit);

        return Inertia::render('Admin/Reports/MostBorrowed', [
            'equipment' => $equipment,
        ]);
    }

    public function monthlyReport(Request $request)
    {
        $year = $request->input('year', date('Y'));

        $data = Borrowing::selectRaw('
                strftime("%m", borrow_date) as month,
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = "returned" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END) as overdue
            ')
            ->whereRaw('strftime("%Y", borrow_date) = ?', [$year])
            ->groupByRaw('strftime("%m", borrow_date)')
            ->orderByRaw('strftime("%m", borrow_date)')
            ->get();

        $years = Borrowing::selectRaw('DISTINCT strftime("%Y", borrow_date) as year')
            ->orderBy('year', 'desc')
            ->pluck('year');

        return Inertia::render('Admin/Reports/MonthlyReport', [
            'data' => $data,
            'year' => $year,
            'years' => $years,
        ]);
    }

    public function activeBorrowers()
    {
        $borrowers = User::withCount(['borrowings as active_borrows' => function ($query) {
            $query->whereIn('status', ['pending_faculty', 'pending_admin', 'approved', 'borrowed', 'overdue']);
        }])
            ->with(['borrowings' => function ($query) {
                $query->whereIn('status', ['pending_faculty', 'pending_admin', 'approved', 'borrowed', 'overdue'])
                    ->with('equipment');
            }])
            ->get()
            ->filter(function ($user) {
                return $user->active_borrows > 0;
            })
            ->sortByDesc('active_borrows')
            ->values();

        return Inertia::render('Admin/Reports/ActiveBorrowers', [
            'borrowers' => $borrowers,
        ]);
    }

    /**
     * Every item currently out with a borrower — one row per equipment
     * line, not per borrowing, since "quantity" and "item name" are
     * per-line details a single borrowing can have several of. Status is
     * shown both as stored and re-derived live from return_date: the
     * stored 'overdue' status only gets set by the daily scheduled command
     * (borrowings:send-overdue-reminders), which depends on a server cron
     * actually being configured — if it hasn't run, a genuinely overdue
     * item would otherwise still show as merely "borrowed" here.
     */
    public function unreturnedItems(Request $request)
    {
        $query = BorrowingEquipment::with(['equipment', 'borrowing.user'])
            ->whereHas('borrowing', function ($q) {
                $q->whereIn('status', ['borrowed', 'overdue']);
            });

        if ($request->campus_id) {
            $query->whereHas('inventory', fn ($q) => $q->where('campus_id', $request->campus_id));
        }

        $items = $query->get()->map(function (BorrowingEquipment $item) {
            $borrowing = $item->borrowing;
            $user = $borrowing?->user;
            $isOverdue = $borrowing?->return_date && $borrowing->return_date->isPast();

            return [
                'borrowing_id'     => $borrowing?->id,
                'borrower_name'    => $user?->name ?? 'Unknown',
                'borrower_id'      => $user?->student_id ?? $user?->employee_id ?? '—',
                'borrower_role'    => $user?->role,
                'equipment_name'   => $item->equipment?->name ?? 'Unknown item',
                'quantity'         => $item->quantity,
                'borrow_date'      => $borrowing?->borrow_date?->toDateString(),
                'return_date'      => $borrowing?->return_date?->toDateString(),
                'status'           => $borrowing?->status,
                'is_overdue'       => $isOverdue,
                'days_overdue'     => $isOverdue ? now()->diffInDays($borrowing->return_date, absolute: true) : 0,
            ];
        })
        ->sortByDesc('is_overdue')
        ->values();

        return Inertia::render('Admin/Reports/UnreturnedItems', [
            'items' => $items,
            'campuses' => \App\Models\Campus::all(['id', 'campus_name']),
            'filters' => $request->only(['campus_id']),
        ]);
    }

    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        if ($request->action) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->paginate(20);
        $users = User::all(['id', 'name']);

        return Inertia::render('Admin/Reports/AuditLogs', [
            'logs' => $logs,
            'users' => $users,
            'filters' => $request->only(['action', 'user_id']),
        ]);
    }
}
