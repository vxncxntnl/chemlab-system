<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\Campus;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
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
            ...$this->accountLimits(),
        ]);
    }

    /**
     * Each role's features depend on specific profile fields: student pages
     * and QR roster checks need campus/student ID/course, faculty need
     * campus/department (hasCompletedProfile()), and every Lab Staff page is
     * scoped by campus_id. Saving a role without its fields previously left
     * the account half-configured for its new role, so they're required per
     * role here, on the backend, regardless of what the form shows.
     */
    private function validateUserData(Request $request, ?User $user, array $passwordRules): array
    {
        $role = $request->input('role');

        $validated = $request->validate([
            'password' => $passwordRules,
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => 'required|in:admin,lab_staff,faculty,student',
            'department' => [Rule::requiredIf($role === 'faculty'), 'nullable', 'string', 'max:255'],
            'course' => ['exclude_unless:role,student', 'required', 'string', 'max:255'],
            'student_id' => [
                'exclude_unless:role,student', 'required', 'string', 'max:50',
                // Roster matching and release verification identify a student
                // by this ID, so two accounts sharing one would be ambiguous.
                Rule::unique('users', 'student_id')->ignore($user?->id)->whereNull('deleted_at'),
            ],
            'employee_id' => 'nullable|string|max:50',
            'contact_number' => 'nullable|string|max:20',
            'campus_id' => [Rule::requiredIf(in_array($role, ['student', 'faculty', 'lab_staff'], true)), 'nullable', 'exists:campuses,id'],
        ], [
            'campus_id.required' => 'A campus is required for Student, Faculty, and Laboratory Staff accounts.',
            'department.required' => 'A department is required for Faculty accounts.',
            'course.required' => 'A course is required for Student accounts.',
            'student_id.required' => 'A Student ID is required for Student accounts.',
            'student_id.unique' => 'This Student ID is already used by another account.',
        ]);

        // Student ID/course only mean something on a student account — left
        // behind on a converted account, release verification would keep
        // checking the old student ID (student_id ?? employee_id).
        if ($validated['role'] !== 'student') {
            $validated['student_id'] = null;
            $validated['course'] = null;
        }

        // A suspended account doesn't hold the campus's lab staff slot.
        $this->enforceAccountLimits($validated['role'], $validated['campus_id'] ?? null, $user, $user?->status ?? 'active');

        return $validated;
    }

    /**
     * One admin for the whole system; one active lab staff per campus.
     * Checked here on the backend for every create, edit and approval —
     * the form only mirrors it.
     */
    private function enforceAccountLimits(string $role, $campusId, ?User $user, string $status): void
    {
        if ($role === 'admin' && ($admin = User::existingAdmin($user?->id))) {
            throw ValidationException::withMessages([
                'role' => "The system already has an admin ({$admin->email}). Only one admin account is allowed.",
            ]);
        }

        if ($role === 'lab_staff' && $status !== 'suspended' && ($staff = User::activeLabStaffAt((int) $campusId, $user?->id))) {
            $campus = Campus::find($campusId)?->campus_name ?? 'This campus';
            throw ValidationException::withMessages([
                'campus_id' => "{$campus} already has an active lab staff account ({$staff->name}). Only one is allowed per campus — suspend or reassign that account first.",
            ]);
        }
    }

    /** What the user form needs to show which roles/campuses are taken. */
    private function accountLimits(?User $editing = null): array
    {
        $admin = User::existingAdmin($editing?->id);

        return [
            'existingAdmin' => $admin ? ['name' => $admin->name, 'email' => $admin->email] : null,
            'labStaffByCampus' => User::where('role', 'lab_staff')
                ->where('status', '!=', 'suspended')
                ->whereNotNull('campus_id')
                ->when($editing, fn ($q) => $q->whereKeyNot($editing->id))
                ->pluck('name', 'campus_id'),
        ];
    }

    public function store(Request $request)
    {
        $validated = $this->validateUserData($request, null, ['required', 'confirmed', Rules\Password::defaults()]);

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
            ...$this->accountLimits($user),
        ]);
    }

    public function update(Request $request, User $user)
    {
        // The role-change guards below run before the per-role field checks,
        // so a disallowed change is reported as such rather than as missing
        // campus/department fields for a role it can't have anyway.
        $newRole = $request->validate(['role' => 'required|in:admin,lab_staff,faculty,student'])['role'];

        // An admin changing their OWN role away from admin, or removing the
        // system's last remaining admin, would either lock the acting admin
        // out mid-edit or leave the system with no admin at all — neither
        // is recoverable without direct database access. Mirrors the
        // existing self-delete/self-suspend guards below.
        if ($user->role === 'admin' && $newRole !== 'admin') {
            if ($user->id === auth()->id()) {
                return back()->with('error', 'You cannot remove your own admin role.');
            }
            if (User::where('role', 'admin')->count() <= 1) {
                return back()->with('error', 'Cannot remove the last remaining admin account.');
            }
        }

        // Requests waiting on this faculty member's approval can only be
        // approved by the assigned faculty (role:faculty + faculty_id match),
        // so demoting them first would strand those requests.
        if ($user->role === 'faculty' && $newRole !== 'faculty') {
            $awaiting = Borrowing::where('faculty_id', $user->id)->where('status', 'pending_faculty')->count();
            if ($awaiting > 0) {
                return back()->withErrors([
                    'role' => "This faculty member still has {$awaiting} borrowing request(s) waiting for their approval. Have them approve or reject those requests (or act on them from Admin > Borrowings) before changing their role.",
                ]);
            }
        }

        $validated = $this->validateUserData($request, $user, ['nullable', 'confirmed', Rules\Password::defaults()]);

        // Blank password field = keep the current one.
        if (filled($validated['password'] ?? null)) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $oldRole = $user->role;

        $user->update($validated);

        AuditLog::log(
            auth()->id(),
            $oldRole !== $user->role ? 'Changed User Role' : 'Updated User',
            'users',
            $user->id,
            $oldRole !== $user->role ? ['role' => $oldRole] : null,
            $oldRole !== $user->role ? ['role' => $user->role] : null,
        );

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
        // Activating must respect the same limits as creating.
        try {
            $this->enforceAccountLimits($user->role, $user->campus_id, $user, 'active');
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

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
