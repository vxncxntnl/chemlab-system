<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            // 'unique' still has to reject a duplicate email outright — two
            // accounts can't share one login identity — so this can't be
            // made fully silent without a much larger rearchitecture
            // (e.g. confirm-by-email-before-account-creation). What we can
            // do is keep the message from bluntly confirming "this account
            // exists", per the custom message below.
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:' . User::class, 'regex:/@phinmaed\.com$/i'],
            'password'   => ['required', 'confirmed', Password::defaults()],
        ], [
            'email.regex' => 'Only PHINMA email addresses (@phinmaed.com) are allowed to register.',
            'email.unique' => 'We couldn\'t register this email. If you already have an account, try logging in or resetting your password instead.',
        ]);

        $user = User::create([
            'name'       => trim($request->first_name . ' ' . $request->last_name),
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => 'student',
            'status'     => 'pending',
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('profile.complete');
    }
}
