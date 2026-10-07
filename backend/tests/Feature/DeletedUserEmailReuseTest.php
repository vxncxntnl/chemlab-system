<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * Deleting a user keeps the account (for history) but releases its email,
 * so the same address can be used again everywhere — faculty add, admin
 * create, sign-up and Google sign-in (which used to crash).
 */
class DeletedUserEmailReuseTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'kyca.panaguiton.coc@phinmaed.com';

    private Campus $campus;
    private User $admin;
    private User $deleted;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutVite();
        $this->campus = Campus::create(['campus_name' => 'Carmen']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->deleted = User::factory()->create([
            'role' => 'student', 'status' => 'active', 'email' => self::EMAIL, 'google_id' => 'g-123',
            'campus_id' => $this->campus->id, 'student_id' => '02-2223-07711', 'course' => 'Civil Engineering',
        ]);
        Borrowing::create(['user_id' => $this->deleted->id, 'borrow_date' => now(), 'return_date' => now()->addDay(), 'status' => 'returned']);

        $this->actingAs($this->admin)->delete("/admin/users/{$this->deleted->id}")->assertSessionHas('success');
        $this->post('/logout');
    }

    public function test_deleting_releases_the_email_but_keeps_the_account_and_history(): void
    {
        $row = User::withTrashed()->find($this->deleted->id);
        $this->assertNotNull($row->deleted_at);
        $this->assertSame(self::EMAIL . '#deleted-' . $this->deleted->id, $row->email);
        $this->assertNull($row->google_id);
        $this->assertSame(1, Borrowing::where('user_id', $this->deleted->id)->count(), 'History is kept.');
    }

    public function test_faculty_can_add_a_student_with_that_email_and_student_id_again(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus->id, 'department' => 'Civil Engineering']);

        $this->actingAs($faculty)->post('/faculty/students', [
            'first_name' => 'Kynt Loige', 'last_name' => 'Panaguiton', 'email' => self::EMAIL, 'student_id' => '02-2223-07711',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(1, User::where('email', self::EMAIL)->count());
    }

    public function test_the_admin_can_create_a_user_with_that_email_again(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Kynt Loige Panaguiton', 'email' => self::EMAIL, 'role' => 'student',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'campus_id' => $this->campus->id, 'student_id' => '02-2223-07711', 'course' => 'Civil Engineering',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', self::EMAIL)->count());
    }

    public function test_the_person_can_sign_up_again_with_that_email(): void
    {
        $this->post('/register', [
            'first_name' => 'Kynt', 'last_name' => 'Panaguiton', 'email' => self::EMAIL,
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('profile.complete'));

        $this->assertSame(1, User::where('email', self::EMAIL)->count());
    }

    public function test_google_sign_in_with_that_email_creates_a_fresh_account_instead_of_crashing(): void
    {
        $google = new SocialiteUser();
        $google->id = 'g-new';
        $google->name = 'Kynt Loige Panaguiton';
        $google->email = self::EMAIL;
        Socialite::shouldReceive('driver->user')->andReturn($google);

        $this->get('/auth/google/callback')->assertRedirect();

        $fresh = User::where('email', self::EMAIL)->sole();
        $this->assertNotSame($this->deleted->id, $fresh->id);
        $this->assertAuthenticatedAs($fresh);
    }

    public function test_the_deleted_account_can_no_longer_sign_in(): void
    {
        $this->post('/login', ['email' => self::EMAIL, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
