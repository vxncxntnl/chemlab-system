<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UserNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index()
    {
        // Notifications of the users this admin manages (their campus).
        $visible = \App\Support\CampusAccess::users(User::withTrashed(), auth()->user())->select('id');
        $notifications = UserNotification::with('user')->whereIn('user_id', $visible)
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function create()
    {
        $users = \App\Support\CampusAccess::users(User::query(), auth()->user())->select('id', 'name', 'email', 'role')->get();

        return Inertia::render('Admin/Notifications/Create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => [\Illuminate\Validation\Rule::in(\App\Support\CampusAccess::users(User::query(), auth()->user())->pluck('id')->all())],
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:info,success,warning,danger',
        ]);

        foreach ($validated['user_ids'] as $userId) {
            UserNotification::create([
                'user_id' => $userId,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'type' => $validated['type'],
            ]);
        }

        AuditLog::log(auth()->id(), 'Sent Notification', 'user_notifications', null, null, [
            'title' => $validated['title'],
            'recipient_count' => count($validated['user_ids']),
        ]);

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Notifications sent successfully.');
    }

    public function destroy(UserNotification $notification)
    {
        AuditLog::log(auth()->id(), 'Deleted Notification', 'user_notifications', $notification->id);

        $notification->delete();

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Notification deleted successfully.');
    }
}
