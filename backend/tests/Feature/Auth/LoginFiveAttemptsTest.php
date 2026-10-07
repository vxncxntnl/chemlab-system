<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 5 wrong passwords per account, then a 5-minute lock — with the attempts
 * left shown after each wrong password.
 */
class LoginFiveAttemptsTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(User $user, string $password, string $ip = '10.0.0.1')
    {
        return $this->from('/login')->post('/login', ['email' => $user->email, 'password' => $password], ['REMOTE_ADDR' => $ip]);
    }

    private function emailError(): string
    {
        return session('errors')->get('email')[0];
    }

    public function test_each_wrong_password_shows_the_attempts_left(): void
    {
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        foreach ([4, 3, 2, 1] as $left) {
            $this->attempt($user, 'wrong')->assertSessionHasErrors('email');
            $this->assertStringContainsString("You have {$left} ".($left === 1 ? 'attempt' : 'attempts').' left', $this->emailError());
        }
    }

    public function test_the_fifth_wrong_password_locks_the_account_for_five_minutes(): void
    {
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        for ($i = 0; $i < 4; $i++) {
            $this->attempt($user, 'wrong');
        }
        $this->attempt($user, 'wrong')->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many failed login attempts', $this->emailError());
        $this->assertStringContainsString('5 minutes', $this->emailError());

        // Even the correct password is refused while locked.
        $this->attempt($user, 'password')->assertSessionHasErrors('email');
        $this->assertGuest();

        // After the lock expires, the correct password works again.
        $this->travel(5)->minutes();
        $this->travel(1)->seconds();
        $this->attempt($user, 'password');
        $this->assertAuthenticatedAs($user);
    }

    public function test_switching_device_does_not_reset_the_count(): void
    {
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        for ($i = 0; $i < 3; $i++) {
            $this->attempt($user, 'wrong', '10.0.0.1');
        }
        $this->attempt($user, 'wrong', '10.0.0.2');
        $this->assertStringContainsString('You have 1 attempt left', $this->emailError());

        $this->attempt($user, 'wrong', '10.0.0.3');
        $this->assertStringContainsString('Too many failed login attempts', $this->emailError());
    }

    public function test_a_correct_login_resets_the_count(): void
    {
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        for ($i = 0; $i < 3; $i++) {
            $this->attempt($user, 'wrong');
        }
        $this->attempt($user, 'password');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');

        $this->attempt($user, 'wrong');
        $this->assertStringContainsString('You have 4 attempts left', $this->emailError());
    }
}
