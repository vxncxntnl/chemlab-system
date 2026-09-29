<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VerifyResetCodeController extends Controller
{
    /**
     * Max wrong-code guesses allowed per email before the outstanding code
     * is burned and a fresh one must be requested. A 6-digit code is only
     * 1,000,000 possibilities — with no per-code attempt cap, the 10/min
     * route throttle alone still allows unlimited guesses spread over the
     * code's 10-minute lifetime.
     */
    private const MAX_ATTEMPTS = 5;

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/VerifyResetCode', [
            'email' => $request->email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $throttleKey = 'reset-code|' . Str::lower($request->string('email'));

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            // Burn the outstanding code too — no point leaving it guessable
            // once the attempt budget for it is spent; the user has to
            // request a fresh one, which also gets a fresh attempt budget.
            PasswordResetCode::where('email', $request->email)->delete();
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'code' => 'Too many incorrect attempts. Please request a new code in ' . ceil($seconds / 60) . ' minute(s).',
            ]);
        }

        $record = PasswordResetCode::where('email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (!$record) {
            // Decay matches the code's own lifetime — the attempt budget
            // for a code doesn't outlive the code itself.
            RateLimiter::hit($throttleKey, 600);
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        if ($record->isExpired()) {
            $record->delete();
            return back()->withErrors(['code' => 'This code has expired. Please request a new one.']);
        }

        RateLimiter::clear($throttleKey);

        // Code is valid — generate a Laravel password reset token
        $user = User::where('email', $request->email)->firstOrFail();
        $token = Password::createToken($user);

        // Delete the used code
        $record->delete();

        return redirect()->route('password.reset', ['token' => $token, 'email' => $request->email]);
    }
}
