<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registration used to accept a 6-character password while every other
 * password entry point (admin-created users, password reset, password
 * change) required Laravel's default minimum of 8. These tests confirm
 * every entry point now rejects a 7-character password consistently.
 */
class PasswordStrengthConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const TOO_SHORT = 'Ab1defg'; // 7 characters

    public function test_registration_rejects_a_password_under_8_characters(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newstudent@phinmaed.com',
            'password' => self::TOO_SHORT,
            'password_confirmation' => self::TOO_SHORT,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_registration_accepts_an_8_character_password(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newstudent2@phinmaed.com',
            'password' => 'Ab1defgh',
            'password_confirmation' => 'Ab1defgh',
        ]);

        $response->assertRedirect(route('profile.complete'));
        $this->assertAuthenticated();
    }

    public function test_admin_creating_a_user_rejects_a_password_under_8_characters(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Staff',
            'email' => 'newstaff@phinmaed.com',
            'password' => self::TOO_SHORT,
            'password_confirmation' => self::TOO_SHORT,
            'role' => 'lab_staff',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_reset_rejects_a_password_under_8_characters(): void
    {
        $user = User::factory()->create();
        $token = \Illuminate\Support\Facades\Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::TOO_SHORT,
            'password_confirmation' => self::TOO_SHORT,
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_authenticated_password_change_rejects_a_password_under_8_characters(): void
    {
        $user = User::factory()->create(['password' => 'CurrentPass1']);

        $response = $this->actingAs($user)->put('/password', [
            'current_password' => 'CurrentPass1',
            'password' => self::TOO_SHORT,
            'password_confirmation' => self::TOO_SHORT,
        ]);

        $response->assertSessionHasErrors('password');
    }
}
