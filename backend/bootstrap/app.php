<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the reverse proxy in front of this app (a CDN, load
        // balancer, or a tunnel like cloudflared) to report the original
        // request's real scheme/host/IP via X-Forwarded-* headers. Without
        // this, Laravel only sees the plain-HTTP hop from the proxy to this
        // server and generates http:// URLs even when the actual client
        // request was https:// — breaking asset loading as mixed content in
        // any browser that enforces it (Safari included). '*' is Laravel's
        // own documented default for proxies whose IP isn't fixed/known in
        // advance, which covers both this tunnel and most real deployments.
        $middleware->trustProxies(at: '*');

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->web(append: [
            \App\Http\Middleware\EnsureAccountIsActive::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            // Inertia keeps each page's data in the browser history so Back
            // can redraw it without asking the server. Encrypting it (the
            // key is cleared on logout) means a logged-out Back/Forward
            // can't redraw a protected page — it re-asks the server, which
            // sends the user to login.
            \Inertia\EncryptHistoryMiddleware::class,
        ]);

        $middleware->alias([
            'role'             => \App\Http\Middleware\RoleMiddleware::class,
            'profile.complete' => \App\Http\Middleware\EnsureProfileComplete::class,
            'admin.campus'     => \App\Http\Middleware\AdminCampusGuard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 = the session (and its CSRF token) expired, e.g. a page left
        // open past SESSION_LIFETIME. Instead of a "Page Expired" error,
        // send the user to log in again with an explanation. JSON callers
        // keep the plain 419.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 419 && !$request->expectsJson()) {
                return redirect()->route('login')->with('status', 'Your session expired. Please log in again.');
            }

            return null;
        });

        // When APP_DEBUG is off, show a clean branded page (with no stack
        // trace, query text, or file paths) instead of Laravel's default
        // error view, for the well-defined HTTP-status exceptions (404, 403,
        // 419, 429, 503, and explicit abort() calls). Deliberately narrow to
        // HttpExceptionInterface only — NOT a catch-all for every Throwable:
        // things like AuthenticationException (redirect to login) and
        // ValidationException (redirect back with errors) are not
        // HttpExceptionInterface and already have specific, correct default
        // handling that must be left alone. For a genuinely unexpected
        // exception, Laravel's own default rendering is already safe once
        // APP_DEBUG is false — it shows a generic error, never internals —
        // so there's no need to intercept that case here too.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (config('app.debug') || app()->runningUnitTests() || $request->expectsJson()) {
                return null; // let Laravel's normal handling take over
            }

            if (!$e instanceof HttpExceptionInterface) {
                return null;
            }

            $status = $e->getStatusCode();

            if ($status < 400) {
                return null;
            }

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
