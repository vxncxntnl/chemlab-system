<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Borrowing;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'isGoogleUser' => !is_null($request->user()->google_id),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Staff accounts are removed by an administrator (Admin > Users),
        // which soft-deletes and keeps their history — and an admin deleting
        // themselves here could leave the system with no administrator.
        if (in_array($user->role, ['admin', 'lab_staff'], true)) {
            return back()->withErrors(['password' => 'Admin and lab staff accounts can\'t be deleted here. Ask an administrator to remove the account from Users management.']);
        }

        // This is a permanent delete, and borrowings.user_id cascades on
        // delete — it would erase the lab's records of what this person
        // borrowed. Accounts with any borrowing history are kept.
        $hasRecords = Borrowing::withTrashed()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('faculty_id', $user->id))
            ->exists();
        if ($hasRecords) {
            return back()->withErrors(['password' => 'Your account has borrowing records that the laboratory must keep, so it can\'t be deleted. Please contact the laboratory administrator.']);
        }

        Auth::logout();

        // A real delete is fine here: this account has no borrowing history
        // to preserve, and it frees the email so the person can sign up again.
        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
