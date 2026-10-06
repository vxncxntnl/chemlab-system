<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_every_page_sends_the_browser_security_headers(): void
    {
        $response = $this->withoutVite()->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->withoutVite()->get('/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->withoutVite()->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
