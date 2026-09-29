<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * The exploit: someone registers via the password form using a real
 * classmate's @phinmaed.com email (which they don't own), picking their own
 * password. Before this fix, the account was immediately usable — able to
 * fill in a profile and reach the dashboard — with no proof the registrant
 * actually owns that mailbox.
 */
class RegistrationEmailOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleUser(string $email): void
    {
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = 'google-' . uniqid();
        $socialiteUser->name = 'Real Owner';
        $socialiteUser->email = $email;

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);
    }

    public function test_registering_with_an_email_does_not_prove_ownership_and_blocks_profile_completion(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'first_name' => 'Squatter',
            'last_name' => 'Attacker',
            'email' => 'victim.student@phinmaed.com',
            'password' => 'attacker-password',
            'password_confirmation' => 'attacker-password',
        ]);

        $user = User::where('email', 'victim.student@phinmaed.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->hasVerifiedEmail(), 'A freshly registered account must start unverified.');

        // The attacker is auto-logged-in (existing behavior) but must not be
        // able to reach the profile form — the real fix is here, not just
        // that a verification record exists somewhere.
        $completeProfile = $this->actingAs($user)->get('/complete-profile');
        $completeProfile->assertRedirect(route('verification.notice'));

        // Same for the dashboard — belt and suspenders, both gates hold.
        $dashboard = $this->actingAs($user)->get('/dashboard');
        $dashboard->assertRedirect(route('verification.notice'));
    }

    public function test_the_verification_email_only_uses_laravels_real_signed_link_mechanism(): void
    {
        // If the attacker can't forge a valid signed link themselves (they
        // can't — it's an HMAC over the real user id/hash using the app
        // key), the only way this account ever gets unblocked is via the
        // literal email Laravel sends to the real mailbox.
        $response = $this->post('/register', [
            'first_name' => 'Squatter',
            'last_name' => 'Attacker',
            'email' => 'another.victim@phinmaed.com',
            'password' => 'attacker-password',
            'password_confirmation' => 'attacker-password',
        ]);

        $user = User::where('email', 'another.victim@phinmaed.com')->first();

        // A forged/guessed verification URL (right shape, wrong signature)
        // must not verify the account.
        $forged = $this->actingAs($user)->get("/verify-email/{$user->id}/" . sha1('not-the-real-hash'));
        $forged->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_google_login_marks_the_email_verified_immediately_since_oauth_already_proves_ownership(): void
    {
        $this->mockGoogleUser('real.google.user@phinmaed.com');

        $this->get('/auth/google/callback');

        $user = User::where('email', 'real.google.user@phinmaed.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasVerifiedEmail(), 'A Google-authenticated user should never be asked to separately verify their email.');

        // And they can reach complete-profile without hitting the verification wall.
        $response = $this->actingAs($user)->get('/complete-profile');
        $response->assertOk();
    }

    public function test_an_existing_unverified_account_becomes_verified_once_its_real_owner_signs_in_via_google(): void
    {
        // Simulates: attacker already registered this email via the
        // password form (unverified row exists); the real owner now signs
        // in with Google for the first time.
        User::factory()->create([
            'email' => 'was.squatted@phinmaed.com',
            'role' => 'student',
            'status' => 'pending',
            'email_verified_at' => null,
        ]);

        $this->mockGoogleUser('was.squatted@phinmaed.com');
        $this->get('/auth/google/callback');

        $user = User::where('email', 'was.squatted@phinmaed.com')->first();
        $this->assertTrue($user->hasVerifiedEmail());
    }
}
