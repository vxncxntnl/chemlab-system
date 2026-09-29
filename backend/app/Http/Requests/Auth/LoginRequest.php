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
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->accountThrottleKey(), self::ACCOUNT_LOCKOUT_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->accountThrottleKey());
    }

    /**
     * Failed-attempt ceiling for the account-wide limiter, independent of
     * which IP the attempts came from. See ensureIsNotRateLimited() for why
     * this exists alongside the per-IP limiter.
     */
    private const ACCOUNT_LOCKOUT_MAX_ATTEMPTS = 10;

    /**
     * Decay window for the account-wide limiter. Short enough that a real
     * account owner is never locked out for long, long enough to make
     * switching IPs a poor way to brute-force one account.
     */
    private const ACCOUNT_LOCKOUT_DECAY_SECONDS = 300;

    /**
     * Ensure the login request is not rate limited.
     *
     * Two independent limiters run here:
     *
     * - throttleKey() (email + IP, 5 attempts / 1 min — the original
     *   behavior): stops a single origin from hammering any one account, and
     *   resets quickly for a legitimate user who mistyped their password.
     * - accountThrottleKey() (email alone, 10 attempts / 5 min — new):
     *   closes the gap the audit found — the per-IP limiter's key changes
     *   every time the attacker switches IP, handing them a fresh 5
     *   attempts each time, so an account could be brute-forced indefinitely
     *   just by rotating source IPs. This second limiter is keyed on the
     *   account only, so it keeps counting no matter which IP the attempts
     *   come from.
     *
     * The account limiter's higher ceiling and short decay are deliberate:
     * it's loose enough that it won't itself lock out the real owner after
     * a few genuine mistakes from multiple devices, but it does mean an
     * attacker COULD trigger a temporary (5-minute, auto-clearing) lockout
     * against a victim's account by deliberately failing logins — an
     * accepted trade-off given the alternative (no cross-IP protection at
     * all) is worse, and the lockout is short and never permanent, per the
     * requirement not to enable an easy denial-of-service or a permanent
     * lock.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->accountThrottleKey(), self::ACCOUNT_LOCKOUT_MAX_ATTEMPTS)) {
            event(new Lockout($this));

            $seconds = RateLimiter::availableIn($this->accountThrottleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
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
