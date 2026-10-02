<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('campus');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->role) {
            $query->where('role', $request->role);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Show pending users first so admin can action them quickly
        $users = $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Admin/Users/Index', [
            'users'   => $users,
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Create', [
            'campuses' => \App\Models\Campus::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:admin,lab_staff,faculty,student',
            'department' => 'nullable|string|max:255',
            'course' => 'nullable|string|max:255',
            'student_id' => 'nullable|string|max:50',
            'employee_id' => 'nullable|string|max:50',
            'contact_number' => 'nullable|string|max:20',
            'campus_id' => 'nullable|exists:campuses,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = 'active'; // Admin-created accounts are active immediately

        $user = User::create($validated);

        AuditLog::log(auth()->id(), 'Created User', 'users', $user->id);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'campuses' => \App\Models\Campus::all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,lab_staff,faculty,student',
            'department' => 'nullable|string|max:255',
            'course' => 'nullable|string|max:255',
            'student_id' => 'nullable|string|max:50',
            'employee_id' => 'nullable|string|max:50',
            'contact_number' => 'nullable|string|max:20',
            'campus_id' => 'nullable|exists:campuses,id',
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Rules\Password::defaults()],
            ]);
            $validated['password'] = Hash::make($request->password);
        }

        // An admin changing their OWN role away from admin, or removing the
        // system's last remaining admin, would either lock the acting admin
        // out mid-edit or leave the system with no admin at all — neither
        // is recoverable without direct database access. Mirrors the
        // existing self-delete/self-suspend guards below.
        if ($user->role === 'admin' && $validated['role'] !== 'admin') {
            if ($user->id === auth()->id()) {
                return back()->with('error', 'You cannot remove your own admin role.');
            }
            if (User::where('role', 'admin')->count() <= 1) {
                return back()->with('error', 'Cannot remove the last remaining admin account.');
            }
        }

        $user->update($validated);

        AuditLog::log(auth()->id(), 'Updated User', 'users', $user->id);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Cannot delete the last remaining admin account.');
        }

        AuditLog::log(auth()->id(), 'Deleted User', 'users', $user->id);

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function approve(User $user)
    {
        $user->update(['status' => 'active']);

        AuditLog::log(auth()->id(), 'Approved User', 'users', $user->id);
        NotificationService::accountApproved($user);

        return back()->with('success', "{$user->name} has been approved and can now log in.");
    }

    public function suspend(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $user->update(['status' => 'suspended']);

        AuditLog::log(auth()->id(), 'Suspended User', 'users', $user->id);
        NotificationService::accountSuspended($user);

        return back()->with('success', "{$user->name} has been suspended.");
    }
}
