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

        // Every role can sign in with email + password (Google Sign-In stays
        // available for PHINMA accounts). Accounts first created via Google
        // set a password through Forgot Password or Profile > Set a Password.
        // The pending/suspended checks below and LoginRequest's rate limiting
        // and account lockout apply to everyone.
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

        // Signed in after scanning an activity QR: continue to that activity.
        if ($continue = \App\Http\Controllers\QrScanController::continuePendingScan($user)) {
            return $continue;
        }

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

        // Sent by the inactivity timer (IdleLogout.vue) so the login page
        // can say why the user was signed out.
        if ($request->input('reason') === 'idle') {
            return redirect()->route('login')->with('status', 'You were logged out due to inactivity. Please log in again.');
        }

        return redirect('/');
    }
}
