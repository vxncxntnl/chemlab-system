<?php

namespace App\Http\Middleware;

use App\Models\Borrowing;
use App\Models\DamageReport;
use App\Models\User;
use App\Support\CampusAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Sidebar badge counts + unread notification count, fetched in a single
     * round trip instead of 2-4 separate queries. Each table's count is a
     * correlated subquery, so this produces identical numbers to the
     * original per-model ->count() calls — including preserving the
     * soft-delete exclusion that Eloquent applies automatically on
     * users/borrowings (damage_reports and user_notifications aren't
     * soft-deletable, so no deleted_at filter is needed for those).
     */
    private function sidebarData(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return ['unread_notifications' => 0, 'sidebar_badges' => []];
        }

        $subqueries = ["(SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND read_at IS NULL) AS unread_notifications"];
        $bindings = [$user->id];

        switch ($user->role) {
            // Admin and lab staff badges count their own campus only —
            // added below with the campus filters.
            case 'faculty':
                $subqueries[] = "(SELECT COUNT(*) FROM borrowings WHERE faculty_id = ? AND status = 'pending_faculty' AND deleted_at IS NULL) AS pending_borrowings";
                $bindings[] = $user->id;
                break;
            case 'student':
                $subqueries[] = "(SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status IN ('borrowed', 'overdue') AND deleted_at IS NULL) AS active_borrowings";
                $bindings[] = $user->id;
                break;
        }

        $row = (array) DB::selectOne('SELECT ' . implode(', ', $subqueries), $bindings);

        if (in_array($user->role, ['admin', 'lab_staff'], true)) {
            $campusId = $user->role === 'admin' ? CampusAccess::adminCampusId($user) : $user->campus_id;
            if ($user->role === 'admin') {
                $row['pending_users'] = CampusAccess::users(User::query(), $user)->where('status', 'pending')->count();
            }
            // Admin: waiting for approval. Lab staff: approved, waiting for release.
            $row['pending_borrowings'] = CampusAccess::borrowings(Borrowing::query(), $campusId)
                ->where('status', $user->role === 'admin' ? 'pending_admin' : 'approved')->count();
            $row['pending_damage_reports'] = CampusAccess::damageReports(DamageReport::query(), $campusId)->where('status', 'pending')->count();
        }

        $unread = (int) $row['unread_notifications'];
        unset($row['unread_notifications']);

        return [
            'unread_notifications' => $unread,
            'sidebar_badges'       => array_map('intval', $row),
        ];
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $sidebar = $this->sidebarData($request);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                // Main campus (Carmen) admin: also sees system setup menus.
                'is_main_admin' => $request->user() ? CampusAccess::isMainAdmin($request->user()) : false,
            ],
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
                'warning' => session('warning'),
                'info'    => session('info'),
            ],
            'unread_notifications' => $sidebar['unread_notifications'],
            'sidebar_badges'       => $sidebar['sidebar_badges'],
        ];
    }
}
