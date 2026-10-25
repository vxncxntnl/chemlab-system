<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\QrScanController;
use App\Models\Campus;
use App\Services\NotificationService;
use App\Support\CampusAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompleteProfileController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        $user = auth()->user();

        if (!$this->mayCompleteProfile($user)) {
            return redirect()->route('dashboard');
        }

        // A student can only join a campus where borrowing is open (the main
        // campus, or one that already has its own admin). The others stay
        // listed but greyed out on the form.
        $campuses = Campus::orderBy('campus_name')->get(['id', 'campus_name'])
            ->map(fn ($campus) => [
                'id'          => $campus->id,
                'campus_name' => $campus->campus_name,
                'open'        => CampusAccess::borrowingOpenAt($campus->id),
            ]);

        return Inertia::render('Auth/CompleteProfile', [
            'campuses' => $campuses,
            'user'     => $user,
        ]);
    }

    /**
     * Only a new student/faculty sign-up that hasn't filled in its profile
     * yet. Without this, anyone signed in could post here and change their
     * own campus or role (e.g. a student switching campus, or an admin/lab
     * staff account turning itself into a student).
     */
    private function mayCompleteProfile($user): bool
    {
        return in_array($user->role, ['student', 'faculty'], true) && !$user->hasCompletedProfile();
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if (!$this->mayCompleteProfile($user)) {
            return redirect()->route('dashboard');
        }

        $role = $request->input('role', 'student');

        if ($role === 'faculty') {
            $request->validate([
                'campus_id'  => 'required|exists:campuses,id',
                'department' => 'required|string|max:100',
            ]);

            $user->update([
                'role'       => 'faculty',
                'campus_id'  => $request->campus_id,
                'department' => $request->department,
                'status'     => 'pending',
            ]);

            $user->load('campus');
            NotificationService::facultyRegistered($user);

            // Faculty accounts require admin verification before use — the
            // registration flow already logged this session in, so log back
            // out immediately rather than leaving them with live access
            // while their status says otherwise. They land on the login
            // screen, which already blocks 'pending' accounts with a clear
            // message, and the existing approve() flow picks up from there.
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Your faculty account has been submitted for verification. You\'ll be able to log in once an administrator approves it.');
        } else {
            $request->validate([
                'campus_id'  => 'required|exists:campuses,id',
                'student_id' => 'required|string|max:50',
                'course'     => 'required|string|max:100',
            ]);

            if (!CampusAccess::borrowingOpenAt((int) $request->campus_id)) {
                return back()->withErrors([
                    'campus_id' => 'This campus is not available yet because it has no administrator. Please choose another campus or try again later.',
                ]);
            }

            $user->update([
                'role'       => 'student',
                'campus_id'  => $request->campus_id,
                'student_id' => $request->student_id,
                'course'     => $request->course,
                'status'     => 'active',
            ]);
        }

        // Continue a QR-code scan that brought the user here to register
        if ($continue = QrScanController::continuePendingScan($user->fresh())) {
            return $continue;
        }

        return redirect()->route('dashboard');
    }
}
