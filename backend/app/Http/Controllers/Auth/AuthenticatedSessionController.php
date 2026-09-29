<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        // The login page labels this form "Admin / Staff login" and points
        // students/faculty to Google Sign-In instead — enforce that here so
        // the restriction is real, not just a UI label.
        //
        // TEMPORARY — DEFENSE DEMO OVERRIDE: DEMO_ALLOW_ALL_ROLE_PASSWORD_LOGIN
        // in .env lets every role use this form, so the demo doesn't depend on
        // venue internet/Google being reachable. Remove this env var (and this
        // comment block) after the defense — the restriction below is a real
        // security control the rest of the time, not a demo convenience.
        if (!env('DEMO_ALLOW_ALL_ROLE_PASSWORD_LOGIN', false) && !in_array($user->role, ['admin', 'lab_staff'], true)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Students and faculty must sign in with your school Google account.']);
        }

        if ($user->status === 'pending') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Your account is pending approval. Please wait for the administrator to review your account.']);
        }

        if ($user->status === 'suspended') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Your account has been suspended. Please contact the laboratory administrator.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
