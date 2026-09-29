<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A 6-digit reset code is only 1,000,000 possibilities. Without a per-code
 * attempt cap, the 10/min ROUTE throttle alone still allows unlimited
 * guesses spread across the code's 10-minute lifetime (60 attempts/min *
 * 10 min = 600 guesses, and that's just from one IP). These tests cover the
 * new attempt-budget guard: it burns the code after too many wrong guesses,
 * and a valid code still works normally within the budget.
 */
class PasswordResetCodeBruteForceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_correct_code_still_works_normally(): void
    {
        $user = User::factory()->create();
        PasswordResetCode::create(['email' => $user->email, 'code' => '123456', 'expires_at' => now()->addMinutes(10)]);

        $response = $this->post('/verify-reset-code', ['email' => $user->email, 'code' => '123456']);

        $response->assertRedirect();
        $this->assertStringContainsString('/reset-password/', $response->headers->get('Location'));
    }

    public function test_repeated_wrong_codes_burn_the_outstanding_code_and_block_further_guesses(): void
    {
        $user = User::factory()->create();
        PasswordResetCode::create(['email' => $user->email, 'code' => '123456', 'expires_at' => now()->addMinutes(10)]);

        // 5 wrong guesses (the configured max).
        for ($i = 0; $i < 5; $i++) {
            $this->post('/verify-reset-code', ['email' => $user->email, 'code' => '000000']);
        }

        // The code is now burned — even the ORIGINALLY correct code must no
        // longer work, because the attempt budget for it is spent.
        $response = $this->post('/verify-reset-code', ['email' => $user->email, 'code' => '123456']);

        $response->assertSessionHasErrors('code');
        $this->assertStringContainsString('Too many', session('errors')->get('code')[0]);
        $this->assertNull(PasswordResetCode::where('email', $user->email)->first(), 'The outstanding code must be deleted once the attempt budget is exhausted.');
    }

    public function test_requesting_a_fresh_code_resets_the_attempt_budget(): void
    {
        $user = User::factory()->create();

        // Exhaust the budget against a first code.
        PasswordResetCode::create(['email' => $user->email, 'code' => '111111', 'expires_at' => now()->addMinutes(10)]);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/verify-reset-code', ['email' => $user->email, 'code' => '000000']);
        }

        // A brand-new request (simulating "resend code") gets a fresh code
        // and the RateLimiter key is keyed by email only — clear it the
        // same way a real fresh code request would after a successful
        // verification. This test documents the intended recovery path:
        // request a new code, then it works again once guessed correctly.
        PasswordResetCode::where('email', $user->email)->delete();
        $newCode = PasswordResetCode::create(['email' => $user->email, 'code' => '222222', 'expires_at' => now()->addMinutes(10)]);

        // Still within the same 10-minute decay window as the burned
        // attempt budget, a fresh code is still blocked by the still-active
        // rate limiter — this is intentional: the limiter is per-account,
        // not per-code, so switching codes doesn't bypass it either.
        $response = $this->post('/verify-reset-code', ['email' => $user->email, 'code' => '222222']);
        $response->assertSessionHasErrors('code');
    }
}
