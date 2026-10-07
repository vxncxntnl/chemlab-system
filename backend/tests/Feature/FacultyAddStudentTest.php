<?php

namespace Tests\Feature;

use App\Mail\BorrowingStatusMail;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Faculty can add student accounts; the student sets their own password
 * (the faculty never sees it) and that also confirms their email.
 */
class FacultyAddStudentTest extends TestCase
{
    use RefreshDatabase;

    private Campus $carmen;
    private User $faculty;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutVite();
        $this->carmen = Campus::create(['campus_name' => 'Carmen']);
        $this->faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->carmen->id, 'department' => 'Nursing', 'name' => 'Prof. Cruz']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Ana', 'last_name' => 'Reyes',
            'email' => 'ana.reyes.coc@phinmaed.com', 'student_id' => '02-2425-00001', 'course' => 'Nursing',
        ];
    }

    public function test_faculty_can_add_a_student_who_gets_an_email_with_sign_in_instructions(): void
    {
        $this->actingAs($this->faculty)->post('/faculty/students', $this->payload())
            ->assertRedirect(route('faculty.students.index'))
            ->assertSessionHas('success');

        $student = User::where('email', 'ana.reyes.coc@phinmaed.com')->sole();
        $this->assertSame('student', $student->role);
        $this->assertSame('active', $student->status);
        $this->assertSame('Ana Reyes', $student->name);
        $this->assertSame($this->carmen->id, $student->campus_id, 'Added to the faculty\'s campus.');
        $this->assertSame('02-2425-00001', $student->student_id);
        $this->assertSame('Nursing', $student->course);
        $this->assertSame($this->faculty->id, $student->created_by);
        $this->assertFalse(Hash::check('password', $student->password), 'A random password, never a known one.');
        $this->assertTrue($student->hasCompletedProfile(), 'No Complete Your Profile step needed.');

        Mail::assertSent(BorrowingStatusMail::class, fn ($m) => $m->hasTo($student->email)
            && str_contains($m->body, 'Prof. Cruz') && str_contains($m->body, 'Forgot password'));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->faculty->id, 'action' => 'Created Student Account (Faculty)', 'record_id' => $student->id]);
    }

    public function test_the_student_sets_their_own_password_which_also_confirms_their_email(): void
    {
        $this->actingAs($this->faculty)->post('/faculty/students', $this->payload())->assertSessionHasNoErrors();
        $this->post('/logout');
        $student = User::where('email', 'ana.reyes.coc@phinmaed.com')->sole();
        $this->assertNull($student->email_verified_at);

        $token = Password::createToken($student);
        $this->post('/reset-password', [
            'token' => $token, 'email' => $student->email,
            'password' => 'my-own-password-1', 'password_confirmation' => 'my-own-password-1',
        ])->assertRedirect(route('login'));

        $this->assertNotNull($student->fresh()->email_verified_at);
        $this->post('/login', ['email' => $student->email, 'password' => 'my-own-password-1']);
        $this->assertAuthenticatedAs($student->fresh());
    }

    public function test_only_phinma_emails_and_no_duplicates_are_accepted(): void
    {
        User::factory()->create(['role' => 'student', 'email' => 'taken.coc@phinmaed.com', 'student_id' => '02-TAKEN']);

        $this->actingAs($this->faculty)->post('/faculty/students', $this->payload(['email' => 'ana@gmail.com']))->assertSessionHasErrors('email');
        $this->actingAs($this->faculty)->post('/faculty/students', $this->payload(['email' => 'taken.coc@phinmaed.com']))->assertSessionHasErrors('email');
        $this->actingAs($this->faculty)->post('/faculty/students', $this->payload(['student_id' => '02-TAKEN']))->assertSessionHasErrors('student_id');
        $this->actingAs($this->faculty)->post('/faculty/students', $this->payload(['first_name' => '', 'student_id' => '']))->assertSessionHasErrors(['first_name', 'student_id']);

        $this->assertSame(0, User::where('created_by', $this->faculty->id)->count());
    }

    public function test_the_course_is_always_the_faculty_department(): void
    {
        $engineer = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->carmen->id, 'department' => 'Civil Engineering']);

        // Even if a different course is sent, the faculty's program is used.
        $this->actingAs($engineer)->post('/faculty/students', $this->payload(['course' => 'Nursing']))->assertSessionHasNoErrors();

        $this->assertSame('Civil Engineering', User::where('email', 'ana.reyes.coc@phinmaed.com')->value('course'));
        $this->actingAs($engineer)->get('/faculty/students/create')->assertInertia(fn ($page) => $page->where('course', 'Civil Engineering'));
    }

    public function test_a_faculty_without_a_department_is_asked_to_contact_the_admin(): void
    {
        $noDept = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->carmen->id, 'department' => null]);

        $this->actingAs($noDept)->post('/faculty/students', $this->payload())->assertSessionHas('error');
        $this->assertNull(User::where('email', 'ana.reyes.coc@phinmaed.com')->first());
    }

    public function test_a_faculty_without_a_campus_is_asked_to_contact_the_admin(): void
    {
        $noCampus = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => null]);

        $this->actingAs($noCampus)->post('/faculty/students', $this->payload())->assertSessionHas('error');
        $this->assertNull(User::where('email', 'ana.reyes.coc@phinmaed.com')->first());
    }

    public function test_the_list_shows_only_the_students_this_faculty_added(): void
    {
        $this->actingAs($this->faculty)->post('/faculty/students', $this->payload())->assertSessionHasNoErrors();
        User::factory()->create(['role' => 'student', 'name' => 'Someone Else']);

        $this->actingAs($this->faculty)->get('/faculty/students')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('students.data', 1)->where('students.data.0.name', 'Ana Reyes'));
    }

    public function test_only_faculty_can_add_students(): void
    {
        foreach (['student', 'lab_staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active', 'campus_id' => $this->carmen->id, 'student_id' => 'S-' . uniqid(), 'course' => 'Nursing']);
            $this->actingAs($user)->post('/faculty/students', $this->payload(['email' => "x{$role}.coc@phinmaed.com"]))->assertForbidden();
        }

        $this->assertSame(0, User::whereNotNull('created_by')->count());
    }
}
