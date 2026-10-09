<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An admin editing their own account could previously demote themselves to
 * another role or delete their own account outright (self-delete was
 * already blocked; self-demotion was not), locking the system out of
 * administration with no recovery path short of direct database access.
 *
 * The "last remaining admin" count check added alongside this is defense
 * in depth for a scenario this app's own authorization model actually
 * makes unreachable today: reaching Admin\UserController at all requires
 * the acting user to already be an admin (role:admin middleware), so a
 * DIFFERENT actor demoting/deleting "the last admin" would require at
 * least two admins to exist simultaneously — at which point it isn't the
 * last one. It's cheap insurance against a future change (a console
 * command, a different actor path) rather than something provable through
 * today's HTTP routes, so it isn't separately tested here.
 */
class AdminSelfRoleRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'faculty',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_an_admin_editing_their_own_non_role_fields_still_works(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'name' => 'Old Name']);

        $response = $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => 'New Name',
            'email' => $admin->email,
            'role' => 'admin', // unchanged
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('New Name', $admin->fresh()->name);
    }

    // (The old "a second admin can demote a different admin" case is gone:
    // the system now allows only one admin — see OneAdminOneLabStaffPerCampusTest.)

    public function test_self_delete_remains_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->delete("/admin/users/{$admin->id}");

        $response->assertSessionHas('error');
        $this->assertNotNull(User::find($admin->id));
    }
}
