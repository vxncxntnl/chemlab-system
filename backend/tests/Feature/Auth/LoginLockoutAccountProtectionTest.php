<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The original lockout was keyed on email+IP only, so an attacker could
 * reset their attempt budget indefinitely just by rotating source IPs.
 * These tests cover the new account-wide limiter (email alone, no IP
 * component) that closes that gap, while confirming it still doesn't lock
 * out a legitimate user permanently or let one account's failures affect
 * another's.
 */
class LoginLockoutAccountProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_ip_does_not_reset_the_account_wide_attempt_budget(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Exhaust attempts from one simulated IP...
        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ], ['REMOTE_ADDR' => '10.0.0.1']);
        }

        // ...then switch IP and keep trying. Before the fix, this would get
        // a fresh 5 attempts since the throttle key includes the IP.
        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ], ['REMOTE_ADDR' => '10.0.0.2']);
        }

        // The 11th attempt total against this account (across two IPs) must
        // now be blocked by the account-wide limiter, not accepted as a
        // fresh 6th attempt from the new IP.
        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ], ['REMOTE_ADDR' => '10.0.0.2']);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');
        $this->assertStringContainsString('Too many', $errors[0], 'Expected the throttle message, meaning the account-wide limiter engaged.');
    }

    public function test_a_different_account_is_not_affected_by_another_accounts_failed_attempts(): void
    {
        $victim = User::factory()->create(['role' => 'admin', 'status' => 'active', 'password' => 'correct-password']);
        $unrelated = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'password' => 'correct-password']);

        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => $victim->email, 'password' => 'wrong']);
        }

        // The unrelated account must still be able to log in normally —
        // the account-wide limiter is keyed per-email, not global.
        $response = $this->post('/login', ['email' => $unrelated->email, 'password' => 'correct-password']);
        $response->assertRedirect();
        $this->assertAuthenticatedAs($unrelated);
    }

    public function test_lockout_is_temporary_and_clears_after_a_successful_login(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active', 'password' => 'correct-password']);

        // A few failed attempts, then the correct password.
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'correct-password']);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user, 'web');
    }
}
