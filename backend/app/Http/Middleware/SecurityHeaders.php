<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standard browser security headers on every response:
 * - X-Frame-Options: the site can't be embedded in another site's frame
 *   (clickjacking).
 * - X-Content-Type-Options: browsers don't guess file types (e.g. run an
 *   uploaded file as a script).
 * - Referrer-Policy: other sites don't see full internal URLs.
 * - Permissions-Policy: the page can't use camera/mic/location — the app
 *   never needs them (students scan QR codes with their phone's camera app).
 * - Strict-Transport-Security: once visited over HTTPS, browsers refuse
 *   plain HTTP for this site.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // Pages shown to a signed-in user must not be kept by the browser,
        // or Back after logout could show them again from its cache.
        // hasUser() only checks a user this request already loaded, so it
        // never starts a session lookup (e.g. on /up); after logout it's
        // false, so the login page keeps its normal caching.
        if (Auth::hasUser()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
