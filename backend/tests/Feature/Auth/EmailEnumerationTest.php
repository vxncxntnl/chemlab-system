<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the forgot-password enumeration fix (the classic account-recovery
 * oracle — worse than registration's, since it directly helps target a
 * real login) and confirms registration's own softened wording.
 */
class EmailEnumerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_gives_the_same_response_for_an_existing_and_a_nonexistent_email(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $existing = $this->post('/forgot-password', ['email' => $user->email]);
        $nonexistent = $this->post('/forgot-password', ['email' => 'no-such-account@phinmaed.com']);

        // Same redirect target, same status, no "no account found" error
        // surfaced for either case.
        $existing->assertRedirect(route('password.verify-code', ['email' => $user->email]));
        $nonexistent->assertRedirect(route('password.verify-code', ['email' => 'no-such-account@phinmaed.com']));
        $existing->assertSessionHasNoErrors();
        $nonexistent->assertSessionHasNoErrors();
    }

    public function test_forgot_password_only_actually_sends_a_code_for_a_real_account(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);
        $this->post('/forgot-password', ['email' => 'no-such-account@phinmaed.com']);

        $this->assertNotNull(PasswordResetCode::where('email', $user->email)->first(), 'A real account must still get a usable code.');
        $this->assertNull(PasswordResetCode::where('email', 'no-such-account@phinmaed.com')->first(), 'No code should ever be generated for an account that does not exist.');
        Mail::assertSent(\App\Mail\PasswordResetCodeMail::class, 1);
    }

    public function test_registering_with_an_already_used_email_does_not_use_a_blunt_already_exists_message(): void
    {
        $existing = User::factory()->create(['email' => 'taken@phinmaed.com']);

        $response = $this->post('/register', [
            'first_name' => 'New',
            'last_name' => 'Person',
            'email' => 'taken@phinmaed.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $message = session('errors')->get('email')[0];
        $this->assertStringNotContainsString('already exists', strtolower($message));
        $this->assertStringNotContainsString('already taken', strtolower($message));
    }
}
