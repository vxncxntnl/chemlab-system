<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->role, ['student', 'faculty'])) {
            if (!$user->hasCompletedProfile() && !$request->routeIs('profile.complete', 'profile.complete.store', 'logout')) {
                return redirect()->route('profile.complete');
            }
        }

        return $next($request);
    }
}
