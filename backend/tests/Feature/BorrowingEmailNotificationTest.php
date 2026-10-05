<?php

namespace Tests\Feature;

use App\Mail\BorrowingStatusMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\Borrowing;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Each borrowing status update emails the borrower using the real user's
 * name, request number and approver, and a mail-server failure is logged
 * instead of silently swallowed (which hid why hosted email never arrived).
 */
class BorrowingEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $faculty;
    private User $admin;
    private User $labStaff;
    private Borrowing $borrowing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => 'Juan dela Cruz']);
        $this->faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'name' => 'Dr. Maria Santos']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'name' => 'Admin Reyes']);
        $this->labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'name' => 'Liza Macaraeg']);
        $this->borrowing = Borrowing::create([
            'user_id' => $this->student->id, 'faculty_id' => $this->faculty->id,
            'borrow_date' => now(), 'return_date' => now()->addDays(2),
            'status' => 'pending_faculty', 'claim_code' => 'CHEM-REQ-TEST-' . uniqid(),
        ]);
    }

    private function assertStudentEmailed(string $expectedText): void
    {
        Mail::assertSent(BorrowingStatusMail::class, function (BorrowingStatusMail $mail) use ($expectedText) {
            return $mail->hasTo($this->student->email)
                && $mail->user->is($this->student)
                && str_contains($mail->body, $expectedText);
        });
    }

    public function test_submitting_a_request_emails_the_student(): void
    {
        Mail::fake();
        NotificationService::borrowingSubmitted($this->borrowing->load('user', 'faculty'));

        $this->assertStudentEmailed("Your borrow request #{$this->borrowing->id} has been submitted successfully. It is now waiting for approval from Dr. Maria Santos.");
    }

    public function test_admin_approval_emails_the_student_with_the_approver_name(): void
    {
        Mail::fake();
        NotificationService::borrowingApprovedByAdmin($this->borrowing->load('user'), $this->admin);

        $this->assertStudentEmailed("Your borrow request #{$this->borrowing->id} has been approved by Admin Reyes. It is now waiting for equipment release by the laboratory staff.");
    }

    public function test_admin_rejection_emails_the_student(): void
    {
        Mail::fake();
        NotificationService::borrowingRejectedByAdmin($this->borrowing->load('user'), $this->admin);

        $this->assertStudentEmailed("Your borrow request #{$this->borrowing->id} has been rejected by Admin Reyes.");
    }

    public function test_release_emails_the_student(): void
    {
        Mail::fake();
        NotificationService::borrowingReleased($this->borrowing->load('user'), $this->labStaff);

        $this->assertStudentEmailed("Your equipment for request #{$this->borrowing->id} has been released by Liza Macaraeg.");
    }

    public function test_return_emails_the_student(): void
    {
        Mail::fake();
        NotificationService::borrowingReturned($this->borrowing->load('user'));

        $this->assertStudentEmailed("Your borrowed equipment for request #{$this->borrowing->id} has been returned successfully.");
    }

    public function test_forgot_password_emails_a_reset_code(): void
    {
        Mail::fake();

        $this->post('/forgot-password', ['email' => $this->student->email])->assertRedirect();

        Mail::assertSent(PasswordResetCodeMail::class, fn ($mail) => $mail->hasTo($this->student->email));
    }

    public function test_the_email_uses_the_lab_system_layout_and_real_names(): void
    {
        $html = (new BorrowingStatusMail(
            $this->student,
            'Borrow Request Fully Approved',
            "Your borrow request #{$this->borrowing->id} has been approved by Admin Reyes. It is now waiting for equipment release by the laboratory staff."
        ))->render();

        $this->assertStringContainsString('Science Laboratory Borrowing System', $html);
        $this->assertStringContainsString('Cagayan de Oro College — PHINMA Education Network', $html);
        $this->assertStringContainsString('Hello, <strong>Juan dela Cruz</strong>!', $html);
        $this->assertStringContainsString("#{$this->borrowing->id} has been approved by Admin Reyes", $html);
        $this->assertStringContainsString('Please do not reply to this email.', $html);
        $this->assertStringContainsString('&copy; ' . date('Y'), $html);
    }

    public function test_a_mail_server_failure_is_logged_but_the_in_app_notification_is_kept(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection could not be established with host "smtp.gmail.com:587"'));
        $reported = [];
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->reportable(function (\RuntimeException $e) use (&$reported) { $reported[] = $e->getMessage(); return false; });

        NotificationService::borrowingReturned($this->borrowing->load('user'));

        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->student->id, 'title' => 'Equipment Returned']);
        $this->assertCount(1, $reported);
    }

    public function test_an_admin_can_send_a_test_email_and_see_the_result(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->post('/admin/settings/test-email')->assertSessionHas('success');

        Mail::assertSent(BorrowingStatusMail::class, fn ($mail) => $mail->hasTo($this->admin->email));
    }

    public function test_a_failed_test_email_shows_the_mail_error_to_the_admin(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection timed out'));

        $this->actingAs($this->admin)->post('/admin/settings/test-email')
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'Connection timed out'));
    }

    public function test_only_admins_can_send_a_test_email(): void
    {
        Mail::fake();

        $this->actingAs($this->student)->post('/admin/settings/test-email')->assertForbidden();

        Mail::assertNothingSent();
    }
}
