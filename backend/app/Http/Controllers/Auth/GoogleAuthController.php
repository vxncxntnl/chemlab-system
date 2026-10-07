<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\QrScanController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in failed. Please try again.']);
        }

        // Restrict to PHINMA email only
        $allowedDomains = ['phinmaed.com', 'phinmaed.edu.ph', 'phinmacoc.edu.ph'];
        $emailDomain = substr(strrchr($googleUser->getEmail(), '@'), 1);

        if (!in_array($emailDomain, $allowedDomains)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Only PHINMA school email addresses are allowed to sign in with Google.',
            ]);
        }

        // Find or create user
        $user = User::where('email', $googleUser->getEmail())->first();

        if (!$user) {
            // Auto-create account for PHINMA users
            $nameParts = explode(' ', $googleUser->getName(), 2);
            $user = User::create([
                'name'              => $googleUser->getName(),
                'first_name'        => $nameParts[0] ?? $googleUser->getName(),
                'last_name'         => $nameParts[1] ?? '',
                'email'             => $googleUser->getEmail(),
                'password'          => bcrypt(\Illuminate\Support\Str::random(32)),
                'role'              => 'student',
                'status'            => 'pending',
                'google_id'         => $googleUser->getId(),
                // Successfully completing Google OAuth for this address IS
                // proof of ownership — just as good as clicking an emailed
                // link, so there's no separate verification step to make a
                // legitimate Google user sit through.
                'email_verified_at' => now(),
            ]);
        } else {
            // Update google_id if not set
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
            // Covers an existing row too — e.g. an account that was
            // originally created via the password form (so it started
            // unverified) but the person now signs in with Google instead:
            // that Google login just as validly proves ownership.
            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        }

        // Block suspended accounts
        if ($user->status === 'suspended') {
            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been suspended. Please contact the administrator.',
            ]);
        }

        // A pending account that hasn't finished Complete Your Profile yet still
        // needs to log in to get there. But a pending account whose profile is
        // already complete (i.e. a faculty account awaiting admin verification)
        // must not proceed any further — otherwise Google sign-in would be a
        // second, unblocked path around the pending-approval gate.
        if ($user->status === 'pending' && $user->hasCompletedProfile()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your account is pending approval. Please wait for the administrator to review your account.',
            ]);
        }

        Auth::login($user, true);

        // NOTE: Auth::login() already rotates the session ID internally —
        // Illuminate\Auth\SessionGuard::login() calls updateSession(), which
        // calls $this->session->regenerate(true) on every login, on every
        // guard, regardless of which controller calls it. So the "Google
        // login doesn't regenerate the session" finding from the earlier
        // audit was actually a false positive: this path was never
        // vulnerable to session fixation, it just didn't have an EXPLICIT
        // second call the way AuthenticatedSessionController::store() does.
        // Keeping one here anyway (harmless — regenerating twice is a no-op
        // beyond the first) so the guarantee is visible in this file too,
        // without relying on a reader knowing SessionGuard's internals.
        $request->session()->regenerate();

        // Continue a QR-code scan that brought the user here to sign in
        if ($continue = QrScanController::continuePendingScan($user)) {
            return $continue;
        }

        // New Google user — redirect to complete profile
        if (!$user->hasCompletedProfile()) {
            return redirect()->route('profile.complete');
        }

        return redirect()->intended('/dashboard');
    }
}
