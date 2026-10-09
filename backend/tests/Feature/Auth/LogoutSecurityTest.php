<?php

namespace Tests\Feature\Auth;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * After logout, Back/Forward/Refresh or a typed URL must not show a protected
 * page again: signed-in pages are sent with no-store, Inertia's page history
 * is encrypted and its key cleared on logout, and the server itself sends
 * every protected route back to login.
 */
class LogoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const DASHBOARDS = [
        'admin' => '/admin/dashboard',
        'lab_staff' => '/lab/dashboard',
        'faculty' => '/faculty/dashboard',
        'student' => '/student/dashboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function makeUser(string $role): User
    {
        $campus = Campus::create(['campus_name' => 'Carmen']);

        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'campus_id' => $campus->id,
            'department' => 'Nursing',
            'student_id' => 'S-' . uniqid(),
            'course' => 'Nursing',
        ]);
    }

    private function login(User $user, ?string $expectedRedirect = null): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect($expectedRedirect ?? route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], array_combine(array_keys(self::DASHBOARDS), array_keys(self::DASHBOARDS)));
    }

    #[DataProvider('roles')]
    public function test_protected_pages_are_not_cached_and_history_is_encrypted(string $role): void
    {
        $this->login($this->makeUser($role));

        $response = $this->get(self::DASHBOARDS[$role])->assertOk();

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertTrue($response->viewData('page')['encryptHistory'], 'Page history is encrypted.');
    }

    #[DataProvider('roles')]
    public function test_after_logout_protected_pages_and_actions_go_to_login(string $role): void
    {
        $user = $this->makeUser($role);
        $this->login($user);
        $this->get(self::DASHBOARDS[$role])->assertOk();

        // Logout ends the session and asks the next page to clear the history key.
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/')->assertRedirect(route('login'));
        $login = $this->get('/login')->assertOk();
        $this->assertTrue($login->viewData('page')['clearHistory'], 'Login page clears the encrypted history.');

        // Refresh / typed URL / Back that re-asks the server: all go to login.
        $this->get(self::DASHBOARDS[$role])->assertRedirect(route('login'));
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/profile')->assertRedirect(route('login'));
        foreach (self::DASHBOARDS as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        // Inertia (XHR) page visits and actions are refused too.
        $this->get(self::DASHBOARDS[$role], ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertRedirect(route('login'));
        $this->post('/student/borrowings', [])->assertRedirect(route('login'));
        $this->post('/admin/borrowings/1/approve')->assertRedirect(route('login'));

        // API endpoints reject the unauthenticated request.
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();

        // Logging in again works normally (Laravel returns the user to the
        // last full page they tried to open — the student dashboard in the
        // loop above; the role check then still applies there).
        $this->login($user, url('/student/dashboard'));
        $this->get(self::DASHBOARDS[$role])->assertOk();
    }

    public function test_idle_logout_still_shows_its_message_and_clears_history(): void
    {
        $this->login($this->makeUser('admin'));

        $this->post('/logout', ['reason' => 'idle'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('inertia.clear_history', true);
        $this->assertGuest();
    }

    public function test_login_page_for_guests_keeps_normal_caching(): void
    {
        $response = $this->get('/login')->assertOk();

        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
