<?php

namespace Tests\Feature;

use App\Mail\BorrowingStatusMail;
use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Emails go to whoever holds a role at the moment of sending — accounts
 * created later included — and the sign-up verification email can't break
 * registration when the mail server is unreachable.
 */
class NewUserEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /** Points the mailer at a closed local port: a real connection failure. */
    private function breakMailServer(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 2,
        ]);
    }

    public function test_lab_staff_and_admins_added_later_receive_role_emails(): void
    {
        Mail::fake();
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $borrowing = Borrowing::create([
            'user_id' => $student->id, 'borrow_date' => now(), 'return_date' => now()->addDay(),
            'status' => 'approved', 'claim_code' => 'CHEM-REQ-TEST-' . uniqid(),
        ]);

        // Accounts that didn't exist when the system was first set up.
        $newLabStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active']);
        $newAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $approver = $newAdmin; // the system's one admin

        NotificationService::borrowingApprovedByAdmin($borrowing->load('user'), $approver);
        NotificationService::borrowingApprovedByFaculty($borrowing->load('user'), $approver);

        Mail::assertSent(BorrowingStatusMail::class, fn ($m) => $m->hasTo($newLabStaff->email));
        Mail::assertSent(BorrowingStatusMail::class, fn ($m) => $m->hasTo($newAdmin->email));
    }

    public function test_a_newly_approved_account_gets_an_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $newUser = User::factory()->create(['role' => 'faculty', 'status' => 'pending']);

        $this->actingAs($admin)->post("/admin/users/{$newUser->id}/approve")->assertSessionHas('success');

        Mail::assertSent(BorrowingStatusMail::class, fn ($m) => $m->hasTo($newUser->email) && $m->title === 'Account Approved');
    }

    public function test_a_new_faculty_registration_emails_the_faculty_and_every_admin(): void
    {
        Mail::fake();
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $admins = User::factory()->count(1)->create(['role' => 'admin', 'status' => 'active']); // the system's one admin
        $newFaculty = User::factory()->create(['role' => 'student', 'status' => 'pending', 'campus_id' => null]);

        $this->actingAs($newFaculty)->post('/complete-profile', [
            'role' => 'faculty', 'campus_id' => $campus->id, 'department' => 'Nursing',
        ])->assertRedirect(route('login'));

        Mail::assertSent(BorrowingStatusMail::class, fn ($m) => $m->hasTo($newFaculty->email));
        foreach ($admins as $admin) {
            Mail::assertSent(BorrowingStatusMail::class, fn ($m) => $m->hasTo($admin->email));
        }
    }

    public function test_signing_up_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'first_name' => 'New', 'last_name' => 'Student', 'email' => 'new.student@phinmaed.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('profile.complete'));

        Notification::assertSentTo(User::where('email', 'new.student@phinmaed.com')->first(), VerifyEmail::class);
    }

    public function test_signing_up_still_works_when_the_mail_server_is_down(): void
    {
        $this->breakMailServer();

        $this->post('/register', [
            'first_name' => 'New', 'last_name' => 'Student', 'email' => 'new.student@phinmaed.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('profile.complete'));

        $this->assertAuthenticated();
    }

    public function test_resending_verification_shows_a_message_when_the_mail_server_is_down(): void
    {
        $this->breakMailServer();
        $user = User::factory()->unverified()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($user)->post('/email/verification-notification')
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-link-failed');
    }

    public function test_borrowing_emails_still_save_the_in_app_notification_when_the_mail_server_is_down(): void
    {
        $this->breakMailServer();
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $borrowing = Borrowing::create([
            'user_id' => $student->id, 'borrow_date' => now(), 'return_date' => now()->addDay(),
            'status' => 'borrowed', 'claim_code' => 'CHEM-REQ-TEST-' . uniqid(),
        ]);

        NotificationService::borrowingReturned($borrowing->load('user'));

        $this->assertDatabaseHas('user_notifications', ['user_id' => $student->id, 'title' => 'Equipment Returned']);
    }
}
