<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Deliberately NOT revealing whether the account exists — this is
        // the classic account-recovery enumeration oracle (worse than
        // registration's, since it directly helps target a real login).
        // Always redirect to the same next step regardless; only actually
        // generate/send a code when the account is real. An attacker
        // probing emails gets identical responses either way, and a
        // legitimate user who mistyped their email still lands on a
        // sensible "check your email" screen instead of a dead end.
        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Delete any previous codes for this email
            PasswordResetCode::where('email', $request->email)->delete();

            // Generate 6-digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            PasswordResetCode::create([
                'email'      => $request->email,
                'code'       => $code,
                'expires_at' => now()->addMinutes(10),
            ]);

            Mail::to($request->email)->send(new PasswordResetCodeMail($code));
        }

        return redirect()->route('password.verify-code', ['email' => $request->email])
            ->with('success', 'If an account exists for that email, a verification code has been sent.');
    }
}
