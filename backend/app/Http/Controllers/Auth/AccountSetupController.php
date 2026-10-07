<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AccountSetupLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

/**
 * The page behind the emailed "Set Your Password" link: the new user picks
 * a password, which activates the account (opening the link proves they
 * own the email) and signs them in.
 */
class AccountSetupController extends Controller
{
    public function show(Request $request, User $user)
    {
        return Inertia::render('Auth/SetupAccount', [
            'valid'  => AccountSetupLink::isValid($request, $user),
            'name'   => $user->name,
            'email'  => $user->email,
            // Posting back to the same signed URL keeps the signature check.
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, User $user)
    {
        if (!AccountSetupLink::isValid($request, $user)) {
            return redirect()->route('login')
                ->with('status', 'That setup link has expired or was already used. Use "Forgot password?" to set your password.');
        }

        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->forceFill([
            'password'          => Hash::make($request->password),
            'remember_token'    => Str::random(60),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        AuditLog::log($user->id, 'Set Password from Setup Link', 'users', $user->id);

        // Signs out whoever was signed in on this browser, then signs the
        // new user in.
        Auth::guard('web')->logout();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Your password is set and your account is ready. Welcome!');
    }
}
