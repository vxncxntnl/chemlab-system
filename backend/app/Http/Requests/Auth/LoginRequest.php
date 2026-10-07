<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Wrong passwords allowed per account before it is locked, and how long
     * the lock lasts. Counted per account (email) across every device/IP, so
     * switching devices doesn't reset the count. A correct login resets it.
     */
    public const MAX_ATTEMPTS = 5;
    public const LOCKOUT_SECONDS = 300;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::LOCKOUT_SECONDS);
            RateLimiter::hit($this->accountThrottleKey(), self::LOCKOUT_SECONDS);

            // The 5th wrong password locks the account straight away.
            $this->ensureIsNotRateLimited();

            $left = self::MAX_ATTEMPTS - RateLimiter::attempts($this->accountThrottleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed').' You have '.$left.' '.($left === 1 ? 'attempt' : 'attempts').' left.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->accountThrottleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * Two limiters with the same limit (MAX_ATTEMPTS wrong passwords, then a
     * LOCKOUT_SECONDS lock):
     *
     * - accountThrottleKey() (email only): the real "5 attempts" rule. It
     *   keeps counting no matter which IP/device the attempts come from, so
     *   rotating IPs can't buy an attacker extra guesses.
     * - throttleKey() (email + IP): kept as a per-origin backstop.
     *
     * The lock is temporary and clears by itself (or the owner can use
     * Forgot password), so it can't be used to lock someone out permanently,
     * and one account's failures never affect another account.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        foreach ([$this->accountThrottleKey(), $this->throttleKey()] as $key) {
            if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                continue;
            }

            event(new Lockout($this));

            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages([
                'email' => 'Too many failed login attempts. Please try again in '.$minutes.' '.($minutes === 1 ? 'minute' : 'minutes').', or use Forgot password.',
            ]);
        }
    }

    /**
     * Get the rate limiting throttle key for the request (per IP + email).
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    /**
     * Get the account-wide rate limiting key (email only, no IP component).
     */
    public function accountThrottleKey(): string
    {
        return 'account|' . Str::transliterate(Str::lower($this->string('email')));
    }
}
