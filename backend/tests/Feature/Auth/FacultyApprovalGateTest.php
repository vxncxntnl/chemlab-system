<?php

namespace Tests\Feature\Auth;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class FacultyApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleUser(string $email, string $googleId = 'google-123'): void
    {
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = $googleId;
        $socialiteUser->name = 'Test Person';
        $socialiteUser->email = $email;

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);
    }

    public function test_completing_profile_as_faculty_sets_pending_and_logs_out(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $response = $this->actingAs($user)->post('/complete-profile', [
            'role' => 'faculty',
            'campus_id' => $campus->id,
            'department' => 'Chemistry',
        ]);

        $user->refresh();
        $this->assertSame('faculty', $user->role);
        $this->assertSame('pending', $user->status);
        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_completing_profile_as_student_stays_active_and_logged_in(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $response = $this->actingAs($user)->post('/complete-profile', [
            'role' => 'student',
            'campus_id' => $campus->id,
            'student_id' => 'S-001',
            'course' => 'BSIT',
        ]);

        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_pending_faculty_with_complete_profile_is_blocked_from_google_login(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $faculty = User::factory()->create([
            'email' => 'pending.faculty@phinmaed.com',
            'role' => 'faculty',
            'status' => 'pending',
            'campus_id' => $campus->id,
            'department' => 'Chemistry',
        ]);

        $this->mockGoogleUser('pending.faculty@phinmaed.com');

        $response = $this->get('/auth/google/callback');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_brand_new_pending_user_can_still_reach_complete_profile_via_google(): void
    {
        $this->mockGoogleUser('new.user@phinmaed.com');

        $response = $this->get('/auth/google/callback');

        // Brand new account: pending, but profile isn't complete yet — must be
        // let through so it can actually get to the complete-profile step.
        $this->assertAuthenticated();
        $response->assertRedirect(route('profile.complete'));
    }

    public function test_approved_faculty_can_log_in_via_google(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        User::factory()->create([
            'email' => 'approved.faculty@phinmaed.com',
            'role' => 'faculty',
            'status' => 'active',
            'campus_id' => $campus->id,
            'department' => 'Chemistry',
        ]);

        $this->mockGoogleUser('approved.faculty@phinmaed.com');

        $response = $this->get('/auth/google/callback');

        $this->assertAuthenticated();
        $response->assertRedirect('/dashboard');
    }

    public function test_student_borrow_form_faculty_dropdown_excludes_pending_faculty(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $activeFaculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $campus->id, 'department' => 'Chemistry']);
        $pendingFaculty = User::factory()->create(['role' => 'faculty', 'status' => 'pending', 'campus_id' => $campus->id, 'department' => 'Biology']);

        $student = User::factory()->create([
            'role' => 'student', 'status' => 'active',
            'campus_id' => $campus->id, 'student_id' => 'S-002', 'course' => 'BSIT',
        ]);

        $response = $this->actingAs($student)->get('/student/borrowings/create');

        $response->assertInertia(fn ($page) => $page
            ->has('faculties', 1)
            ->where('faculties.0.id', $activeFaculty->id)
        );
    }

    public function test_admin_activity_instructor_dropdown_excludes_pending_faculty(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $activeFaculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $campus->id, 'department' => 'Chemistry']);
        User::factory()->create(['role' => 'faculty', 'status' => 'pending', 'campus_id' => $campus->id, 'department' => 'Biology']);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->get('/admin/activities/create');

        $response->assertInertia(fn ($page) => $page
            ->has('instructors', 1)
            ->where('instructors.0.id', $activeFaculty->id)
        );
    }
}
