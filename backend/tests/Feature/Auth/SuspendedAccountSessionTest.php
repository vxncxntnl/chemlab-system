<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login already refuses a suspended account — but that only guards the
 * moment of signing in. These tests cover the gap the audit found: an
 * account suspended by an admin WHILE the user already has an active
 * session must lose access on the very next request, not keep working
 * until they happen to log out and back in.
 */
class SuspendedAccountSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_suspended_users_existing_session_is_rejected_on_the_next_request(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Simulate an already-logged-in session.
        $this->actingAs($user);

        // Admin action happens out-of-band (e.g. another admin suspends
        // this account) — the row changes underneath the live session.
        $user->update(['status' => 'suspended']);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_a_suspended_users_session_is_rejected_on_role_gated_routes_too(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($user);
        $user->update(['status' => 'suspended']);

        $response = $this->get('/admin/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_an_active_users_session_is_completely_unaffected(): void
    {
        // /dashboard itself just role-redirects (e.g. admin -> /admin/dashboard),
        // so hit the role-specific route directly to check for a real 200,
        // not a redirect that would also happen to a suspended user.
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($user);

        $response = $this->get('/admin/dashboard');

        $response->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_a_pending_users_session_is_not_affected_by_the_suspended_check(): void
    {
        // The new middleware deliberately only targets 'suspended', not
        // 'pending' — login already blocks pending accounts outright, so
        // there's no scenario where a pending account holds a live session
        // to begin with. This documents that scope boundary.
        $user = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        // Not rejected by the suspended-account middleware — whatever
        // happens next is governed by other logic (profile-complete, etc.),
        // not a redirect-to-login from EnsureAccountIsActive.
        if ($response->isRedirect()) {
            $this->assertNotSame(route('login'), $response->headers->get('Location'));
        }
        $this->assertAuthenticatedAs($user, 'web');
    }
}
