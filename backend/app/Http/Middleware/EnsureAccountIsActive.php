<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login already refuses a suspended account (AuthenticatedSessionController)
 * — but that only guards the moment of signing in. Laravel sessions don't
 * automatically notice a later change to the underlying user row, so an
 * account suspended by an admin WHILE the user is already logged in could
 * keep using that session indefinitely; nothing re-checked status on
 * subsequent requests. Registered globally on the 'web' group (not tied to
 * 'role:' middleware) so it covers every authenticated route, including the
 * ones that only require 'auth' — /dashboard, /profile, the notifications
 * API — not just the role-gated ones.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status === 'suspended') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account has been suspended.'], 403);
            }

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been suspended. Please contact the laboratory administrator.']);
        }

        return $next($request);
    }
}
