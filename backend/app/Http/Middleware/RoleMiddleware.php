<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        if (!in_array($request->user()->role, $roles)) {
            // An in-app click (Inertia visit) from a page that no longer
            // matches the user's role — typically because an admin changed
            // their role while they were signed in — goes to the dashboard
            // for their current role with a message, instead of an error
            // modal. Access is still denied either way.
            if ($request->header('X-Inertia')) {
                return redirect()->route('dashboard')
                    ->with('error', 'You no longer have access to that page. Your account role may have been changed.');
            }

            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}
