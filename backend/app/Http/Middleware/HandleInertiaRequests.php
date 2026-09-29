<?php

namespace App\Http\Middleware;

use App\Models\Borrowing;
use App\Models\DamageReport;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
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
     * Determine the current asset version.
     */
    private function sidebarBadges(Request $request): array
    {
        $user = $request->user();
        if (!$user) return [];

        return match ($user->role) {
            'admin' => [
                'pending_users'          => User::where('status', 'pending')->count(),
                'pending_borrowings'     => Borrowing::where('status', 'pending_admin')->count(),
                'pending_damage_reports' => DamageReport::where('status', 'pending')->count(),
            ],
            'lab_staff' => [
                // Lab staff no longer approve requests; the badge now reflects
                // approved requests waiting to be released to the borrower.
                'pending_borrowings'     => Borrowing::where('status', 'approved')->count(),
                'pending_damage_reports' => DamageReport::where('status', 'pending')->count(),
            ],
            'faculty' => [
                'pending_borrowings' => Borrowing::where('faculty_id', $user->id)->where('status', 'pending_faculty')->count(),
            ],
            'student' => [
                'active_borrowings' => Borrowing::where('user_id', $user->id)->whereIn('status', ['borrowed', 'overdue'])->count(),
            ],
            default => [],
        };
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
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
                'warning' => session('warning'),
                'info'    => session('info'),
            ],
            'unread_notifications' => $request->user()
                ? UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count()
                : 0,
            'sidebar_badges' => $this->sidebarBadges($request),
        ];
    }
}
