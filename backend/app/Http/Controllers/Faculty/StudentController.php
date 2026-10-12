<?php

namespace App\Http\Controllers\Faculty;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Faculty can add student accounts (e.g. for a class whose students haven't
 * signed up yet). The faculty never sets or sees the password: the account
 * gets a random one and the student sets their own through Forgot password
 * (which also confirms their email) or signs in with Google.
 */
class StudentController extends Controller
{
    public function index(Request $request)
    {
        $students = User::where('role', 'student')
            ->where('created_by', auth()->id())
            ->when($request->search, fn ($q, $search) => $q->where(fn ($q) => $q
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")
                ->orWhereLike('student_id', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Faculty/Students/Index', [
            'students' => $students,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Faculty/Students/Create', [
            'course' => auth()->user()->department,
            'campus' => auth()->user()->campus,
        ]);
    }

    public function store(Request $request)
    {
        $faculty = auth()->user();

        // The student belongs to the faculty's campus and program, so the
        // faculty needs both set.
        if (!$faculty->campus_id) {
            return back()->with('error', 'Your account has no campus yet. Please ask the administrator to set your campus first.');
        }
        if (!$faculty->department) {
            return back()->with('error', 'Your account has no department yet. Please ask the administrator to set your department first.');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            // Unique across all accounts (including removed ones) so a
            // faculty-added account can't collide with a self-registered one.
            'email'      => ['required', 'string', 'email', 'max:255', 'regex:/@phinmaed\.com$/i', Rule::unique('users', 'email')],
            'student_id' => ['required', 'string', 'max:50', Rule::unique('users', 'student_id')->whereNull('deleted_at')],
        ], [
            'email.regex'       => 'Only PHINMA school emails (@phinmaed.com) can be used.',
            'email.unique'      => 'An account with this email already exists.',
            'student_id.unique' => 'This Student ID is already used by another account.',
        ]);

        $student = User::create([
            'name'       => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => Str::lower($validated['email']),
            // Random and never shown: the student sets their own password.
            'password'   => Hash::make(Str::random(40)),
            'role'       => 'student',
            'status'     => 'active',
            'campus_id'  => $faculty->campus_id,
            'student_id' => $validated['student_id'],
            // Always the faculty's own program (e.g. a Civil Engineering
            // faculty adds Civil Engineering students), never chosen.
            'course'     => $faculty->department,
            'created_by' => $faculty->id,
        ]);

        AuditLog::log($faculty->id, 'Created Student Account (Faculty)', 'users', $student->id, null, [
            'email' => $student->email,
            'student_id' => $student->student_id,
        ]);

        NotificationService::studentAccountCreated($student, $faculty);

        return redirect()->route('faculty.students.index')
            ->with('success', "Student account created for {$student->name}. They've been emailed a link to set their password.");
    }

    /**
     * Send a fresh "Set Your Password" link (e.g. the first one expired or
     * got lost) — only for a student this faculty added who hasn't set a
     * password yet.
     */
    public function resendSetupLink(User $student)
    {
        if ($student->role !== 'student' || $student->created_by !== auth()->id()) {
            abort(403);
        }
        if ($student->email_verified_at) {
            return back()->with('info', "{$student->name} has already set their password.");
        }

        NotificationService::studentAccountCreated($student, auth()->user());
        AuditLog::log(auth()->id(), 'Resent Student Setup Link (Faculty)', 'users', $student->id);

        return back()->with('success', "A new set-password link was emailed to {$student->name}.");
    }
}
