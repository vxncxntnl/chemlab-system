<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registration and the final password-reset submission had no throttle
 * middleware at all before this fix — an attacker could spam either
 * endpoint without limit. These confirm both are now capped at 10/min
 * (matching the other auth endpoints' throttle) while staying well clear
 * of normal single-submission use.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_rate_limited_after_10_requests_per_minute(): void
    {
        // Each request deliberately fails validation (mismatched password
        // confirmation) so no account is ever created and no login happens
        // — a successful registration would log the client in, and the
        // 'guest' middleware wrapping this route would then redirect every
        // subsequent request away before it ever reached the throttle
        // check, making the throttle impossible to observe this way.
        for ($i = 0; $i < 10; $i++) {
            $this->post('/register', [
                'first_name' => 'Spam', 'last_name' => 'Bot',
                'email' => "spam{$i}@phinmaed.com",
                'password' => 'a-strong-password', 'password_confirmation' => 'does-not-match',
            ]);
        }

        $response = $this->post('/register', [
            'first_name' => 'Spam', 'last_name' => 'Bot',
            'email' => 'spam-overflow@phinmaed.com',
            'password' => 'a-strong-password', 'password_confirmation' => 'does-not-match',
        ]);

        $response->assertStatus(429);
    }

    public function test_a_single_legitimate_registration_is_not_throttled(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Real', 'last_name' => 'Student',
            'email' => 'realstudent@phinmaed.com',
            'password' => 'a-strong-password', 'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertRedirect(route('profile.complete'));
    }

    public function test_final_password_reset_submission_is_rate_limited_after_10_requests_per_minute(): void
    {
        $user = User::factory()->create();
        $token = \Illuminate\Support\Facades\Password::createToken($user);

        for ($i = 0; $i < 10; $i++) {
            $this->post('/reset-password', [
                'token' => $token, 'email' => $user->email,
                'password' => 'wrong-confirmation', 'password_confirmation' => 'does-not-match',
            ]);
        }

        $response = $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'wrong-confirmation', 'password_confirmation' => 'does-not-match',
        ]);

        $response->assertStatus(429);
    }
}
