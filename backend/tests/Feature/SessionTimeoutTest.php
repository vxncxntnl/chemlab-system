<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inactivity logout (IdleLogout.vue posts logout with reason=idle) and an
 * expired session (419) both land on the login page with a message
 * instead of an error screen.
 */
class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_idle_logout_goes_to_login_with_an_inactivity_message(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($user)->post('/logout', ['reason' => 'idle'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'You were logged out due to inactivity. Please log in again.');

        $this->assertGuest();
    }

    public function test_a_normal_logout_is_unchanged(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($user)->post('/logout')->assertRedirect('/')->assertSessionMissing('status');
        $this->assertGuest();
    }

    public function test_an_expired_session_redirects_to_login_instead_of_a_419_page(): void
    {
        // Real CSRF checking, with no token in the session — what happens
        // when the session has expired.
        $this->withMiddleware(ValidateCsrfToken::class);
        $this->app->detectEnvironment(fn () => 'production');

        $this->post('/forgot-password', ['email' => 'someone@phinmaed.com'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your session expired. Please log in again.');
    }
}
