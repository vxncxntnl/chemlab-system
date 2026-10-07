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
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('student_id', 'like', "%{$search}%")))
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
            'defaultCourse' => auth()->user()->department,
            'campus' => auth()->user()->campus,
        ]);
    }

    public function store(Request $request)
    {
        $faculty = auth()->user();

        // The student belongs to the faculty's campus, so the faculty needs one.
        if (!$faculty->campus_id) {
            return back()->with('error', 'Your account has no campus yet. Please ask the administrator to set your campus first.');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            // Unique across all accounts (including removed ones) so a
            // faculty-added account can't collide with a self-registered one.
            'email'      => ['required', 'string', 'email', 'max:255', 'regex:/@phinmaed\.com$/i', Rule::unique('users', 'email')],
            'student_id' => ['required', 'string', 'max:50', Rule::unique('users', 'student_id')->whereNull('deleted_at')],
            'course'     => 'required|string|max:255',
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
            'course'     => $validated['course'],
            'created_by' => $faculty->id,
        ]);

        AuditLog::log($faculty->id, 'Created Student Account (Faculty)', 'users', $student->id, null, [
            'email' => $student->email,
            'student_id' => $student->student_id,
        ]);

        NotificationService::studentAccountCreated($student, $faculty);

        return redirect()->route('faculty.students.index')
            ->with('success', "Student account created for {$student->name}. They've been emailed instructions to set their password.");
    }
}
