<?php

namespace Tests\Feature;

use App\Mail\AccountSetupMail;
use App\Models\Campus;
use App\Models\User;
use App\Support\AccountSetupLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The emailed "Set Your Password" link for faculty-added students: opens a
 * set + confirm password page, signs them in, and is single-use and
 * expiring.
 */
class AccountSetupLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $faculty;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutVite();
        $campus = Campus::create(['campus_name' => 'Carmen']);
        $this->faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $campus->id, 'department' => 'Nursing', 'name' => 'Prof. Cruz']);
        $this->student = User::factory()->unverified()->create([
            'role' => 'student', 'status' => 'active', 'campus_id' => $campus->id, 'student_id' => 'S-1', 'course' => 'Nursing',
            'name' => 'Ana Reyes', 'email' => 'ana.reyes.coc@phinmaed.com', 'password' => Hash::make(Str::random(40)),
            'created_by' => $this->faculty->id,
        ]);
    }

    private function relative(string $url): string
    {
        return Str::after($url, url('/'));
    }

    private function setPassword(string $link, string $password = 'my-new-pass-1', ?string $confirm = null)
    {
        return $this->post($this->relative($link), ['password' => $password, 'password_confirmation' => $confirm ?? $password]);
    }

    public function test_the_email_has_a_set_your_password_button_with_the_link(): void
    {
        $link = AccountSetupLink::for($this->student);
        $html = (new AccountSetupMail($this->student, 'Prof. Cruz', $link))->render();

        $this->assertStringContainsString('Set Your Password', $html);
        $this->assertStringContainsString(e($link), $html);
        $this->assertStringContainsString('Prof. Cruz created an account for you', $html);
        $this->assertStringContainsString('expires in 7 days', $html);
    }

    public function test_the_link_opens_the_set_password_page(): void
    {
        $this->get($this->relative(AccountSetupLink::for($this->student)))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('valid', true)
                ->where('name', 'Ana Reyes')
                ->where('email', 'ana.reyes.coc@phinmaed.com'));
    }

    public function test_setting_the_password_activates_the_account_and_signs_the_student_in(): void
    {
        $this->setPassword(AccountSetupLink::for($this->student))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $student = $this->student->fresh();
        $this->assertTrue(Hash::check('my-new-pass-1', $student->password));
        $this->assertNotNull($student->email_verified_at, 'Opening the emailed link proves they own the email.');
        $this->assertAuthenticatedAs($student);
    }

    public function test_the_link_works_only_once(): void
    {
        $link = AccountSetupLink::for($this->student);
        $this->setPassword($link)->assertRedirect(route('dashboard'));
        $this->post('/logout');

        $this->get($this->relative($link))->assertInertia(fn ($page) => $page->where('valid', false));
        $this->setPassword($link, 'someone-else-9')->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('my-new-pass-1', $this->student->fresh()->password), 'The password set first stays.');
    }

    public function test_the_link_expires_after_7_days(): void
    {
        $link = AccountSetupLink::for($this->student);
        $this->travel(7)->days();
        $this->travel(1)->minutes();

        $this->get($this->relative($link))->assertInertia(fn ($page) => $page->where('valid', false));
        $this->setPassword($link)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_edited_link_is_rejected(): void
    {
        $other = User::factory()->unverified()->create(['role' => 'student', 'created_by' => $this->faculty->id]);
        $link = AccountSetupLink::for($this->student);
        $edited = str_replace('/account/setup/' . $this->student->id, '/account/setup/' . $other->id, $link);

        $this->get($this->relative($edited))->assertInertia(fn ($page) => $page->where('valid', false));
        $this->setPassword($edited)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_two_passwords_must_match(): void
    {
        $this->setPassword(AccountSetupLink::for($this->student), 'my-new-pass-1', 'different-pass-2')
            ->assertSessionHasErrors('password');
        $this->assertNull($this->student->fresh()->email_verified_at);
        $this->assertGuest();
    }

    public function test_faculty_can_resend_the_link_to_their_own_waiting_student(): void
    {
        $this->actingAs($this->faculty)->post("/faculty/students/{$this->student->id}/resend-setup")->assertSessionHas('success');
        Mail::assertSent(AccountSetupMail::class, fn ($m) => $m->hasTo($this->student->email));

        $otherFaculty = User::factory()->create(['role' => 'faculty', 'status' => 'active']);
        $this->actingAs($otherFaculty)->post("/faculty/students/{$this->student->id}/resend-setup")->assertForbidden();
    }

    public function test_no_resend_once_the_student_has_set_their_password(): void
    {
        $this->student->update(['email_verified_at' => now()]);

        $this->actingAs($this->faculty)->post("/faculty/students/{$this->student->id}/resend-setup")->assertSessionHas('info');
        Mail::assertNothingSent();
    }
}
