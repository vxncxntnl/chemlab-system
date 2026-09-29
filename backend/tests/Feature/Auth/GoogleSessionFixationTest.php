<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleSessionFixationTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleUser(string $email): void
    {
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = 'google-' . uniqid();
        $socialiteUser->name = 'Test Person';
        $socialiteUser->email = $email;

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);
    }

    /**
     * Responses carry more than one cookie (XSRF-TOKEN, the session cookie,
     * possibly others) — pulling getCookies()[0] blindly is not reliable,
     * since order isn't guaranteed. And critically: Laravel's EncryptCookies
     * middleware encrypts the raw cookie value with a fresh random IV on
     * every single response, so the ciphertext differs between requests
     * EVEN WHEN the underlying session ID is identical — comparing the raw
     * cookie strings would make this test pass regardless of whether the
     * fix exists. Decrypting back to the real session ID is what actually
     * makes the comparison meaningful.
     */
    private function rawSessionCookie($response, string $cookieName): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie->getValue();
            }
        }
        return null;
    }

    private function decryptedSessionId(string $rawCookieValue): string
    {
        return Crypt::decrypt($rawCookieValue, false);
    }

    public function test_the_session_id_changes_after_a_successful_google_login(): void
    {
        // The 'array' driver used by default in tests doesn't persist state
        // across separate test-client calls the way 'database' (the real
        // configured driver, per .env) does — use the real driver here so
        // this test reflects actual session-cookie behavior.
        config(['session.driver' => 'database']);

        User::factory()->create([
            'email' => 'fixation.check@phinmaed.com',
            'role' => 'student',
            'status' => 'active',
        ]);

        // Establish a session before authentication and capture its real
        // cookie value — this is the "pre-login" session ID an attacker
        // would try to plant/reuse in a real fixation attack. Each test
        // HTTP call is otherwise independent (Laravel's test client doesn't
        // auto-carry cookies between calls), so the pre-login cookie has to
        // be explicitly resent on the next request to actually simulate
        // "same browser, same session, before vs. after login."
        $cookieName = config('session.cookie');
        $preLoginResponse = $this->get('/login');
        $rawPreLoginCookie = $this->rawSessionCookie($preLoginResponse, $cookieName);
        $this->assertNotEmpty($rawPreLoginCookie, 'Precondition failed: no session cookie was issued to compare against.');
        $preLoginSessionId = $this->decryptedSessionId($rawPreLoginCookie);

        // Resend the RAW (still-encrypted) cookie, exactly as a browser
        // would — only the comparison itself needs the decrypted ID.
        $this->mockGoogleUser('fixation.check@phinmaed.com');
        $postLoginResponse = $this->withCookie($cookieName, $rawPreLoginCookie)->get('/auth/google/callback');
        $rawPostLoginCookie = $this->rawSessionCookie($postLoginResponse, $cookieName);
        $this->assertNotEmpty($rawPostLoginCookie, 'Precondition failed: no session cookie was issued on the post-login response.');
        $postLoginSessionId = $this->decryptedSessionId($rawPostLoginCookie);

        $this->assertNotSame(
            $preLoginSessionId,
            $postLoginSessionId,
            'The session ID must be rotated after Google login, or a pre-login session ID stays valid post-login (session fixation).'
        );
        $this->assertAuthenticated();
    }
}
