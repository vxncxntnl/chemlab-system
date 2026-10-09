<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Equipment;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\CampusAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(private InventoryService $inventoryService)
    {
    }

    /** Every report covers the signed-in admin's campus only. */
    private function campusId(): ?int
    {
        return CampusAccess::adminCampusId(auth()->user());
    }

    public function lowStock()
    {
        $items = $this->inventoryService->getLowStockItems($this->campusId());

        return Inertia::render('Admin/Reports/LowStock', [
            'items' => $items,
        ]);
    }

    public function mostBorrowed(Request $request)
    {
        $limit = $request->input('limit', 10);
        $equipment = $this->inventoryService->getMostBorrowedEquipment((int) $limit, $this->campusId());

        return Inertia::render('Admin/Reports/MostBorrowed', [
            'equipment' => $equipment,
        ]);
    }

    public function monthlyReport(Request $request)
    {
        $year = $request->input('year', date('Y'));

        // PostgreSQL (hosted) uses EXTRACT(); SQLite (used by
        // tests) has no EXTRACT/::int and uses strftime() instead. Also
        // note: PostgreSQL treats double-quoted text as a column/identifier
        // name, not a string literal, so the status comparisons below must
        // use single quotes.
        $isPostgres = Borrowing::query()->getConnection()->getDriverName() === 'pgsql';
        $monthExpr = $isPostgres ? 'EXTRACT(MONTH FROM borrow_date)::int' : "CAST(strftime('%m', borrow_date) AS INTEGER)";
        $yearExpr = $isPostgres ? 'EXTRACT(YEAR FROM borrow_date)::int' : "CAST(strftime('%Y', borrow_date) AS INTEGER)";

        $campusId = $this->campusId();
        $data = CampusAccess::borrowings(Borrowing::query(), $campusId)->selectRaw("
                {$monthExpr} as month,
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue
            ")
            ->whereRaw("{$yearExpr} = ?", [(int) $year])
            ->groupByRaw($monthExpr)
            ->orderByRaw($monthExpr)
            ->get();

        $years = CampusAccess::borrowings(Borrowing::query(), $campusId)->selectRaw("DISTINCT {$yearExpr} as year")
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
        $campusId = $this->campusId();
        $borrowers = User::withCount(['borrowings as active_borrows' => function ($query) use ($campusId) {
            CampusAccess::borrowings($query, $campusId)->whereIn('status', ['pending_faculty', 'pending_admin', 'approved', 'borrowed', 'overdue']);
        }])
            ->with(['borrowings' => function ($query) use ($campusId) {
                CampusAccess::borrowings($query, $campusId)->whereIn('status', ['pending_faculty', 'pending_admin', 'approved', 'borrowed', 'overdue'])
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

        // Always this admin's campus (the campus filter can't widen it).
        $campusId = $this->campusId();
        $query->whereHas('inventory', fn ($q) => $q->where('campus_id', $campusId));

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
            'campuses' => \App\Models\Campus::whereKey($campusId)->get(['id', 'campus_name']),
            'filters' => $request->only(['campus_id']),
        ]);
    }

    public function auditLogs(Request $request)
    {
        $query = CampusAccess::auditLogs(AuditLog::with('user:id,name,role,department,course'), auth()->user())->orderBy('created_at', 'desc');

        if ($request->action) {
            // Ignores upper/lower case ("created" finds "Created User").
            $query->whereRaw('LOWER(action) LIKE ?', ['%' . mb_strtolower($request->action) . '%']);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Department for faculty, course for students.
        if ($request->department) {
            $query->whereHas('user', fn ($u) => $u->withTrashed()->where(fn ($u) => $u
                ->where(fn ($u) => $u->where('role', 'faculty')->where('department', $request->department))
                ->orWhere(fn ($u) => $u->where('role', 'student')->where('course', $request->department))));
        }

        $logs = $query->paginate(20)->withQueryString();
        $logs->getCollection()->each(fn (AuditLog $log) => $log->setAttribute('department', User::departmentOf($log->user)));

        $users = CampusAccess::users(User::query(), auth()->user())->orderBy('name')->get(['id', 'name', 'role', 'department', 'course']);

        return Inertia::render('Admin/Reports/AuditLogs', [
            'logs' => $logs,
            'users' => $users->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'department' => User::departmentOf($u)]),
            'departments' => $users->map(fn (User $u) => User::departmentOf($u))->filter()->unique()->sort()->values(),
            'filters' => $request->only(['action', 'user_id', 'department']),
        ]);
    }
}
