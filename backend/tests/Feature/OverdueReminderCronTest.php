<?php

namespace Tests\Feature;

use App\Mail\OverdueReminder;
use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The hosted server has no cron or queue worker, so overdue reminders are
 * triggered by an external scheduler calling a secret URL and are sent
 * immediately instead of being queued.
 */
class OverdueReminderCronTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cron.secret' => 'test-secret-123']);
        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active']);
    }

    private function borrowingDue(\DateTimeInterface $returnDate, string $status = 'borrowed'): Borrowing
    {
        return Borrowing::create([
            'user_id' => $this->student->id, 'borrow_date' => now()->subDays(5), 'return_date' => $returnDate,
            'status' => $status, 'claim_code' => 'CHEM-REQ-TEST-' . uniqid(),
        ]);
    }

    public function test_the_secret_url_sends_overdue_and_due_tomorrow_reminders_immediately(): void
    {
        Mail::fake();
        $overdue = $this->borrowingDue(now()->subDays(2));
        $this->borrowingDue(now()->addDay());

        $this->get('/cron/overdue-reminders/test-secret-123')->assertOk();

        Mail::assertSent(OverdueReminder::class, 2);
        Mail::assertNothingQueued();
        $this->assertSame('overdue', $overdue->fresh()->status);
    }

    public function test_a_second_call_on_the_same_day_does_not_email_again(): void
    {
        Mail::fake();
        $this->borrowingDue(now()->subDays(2));

        $this->get('/cron/overdue-reminders/test-secret-123')->assertOk();
        $this->get('/cron/overdue-reminders/test-secret-123')->assertOk()->assertSee('already sent today');

        Mail::assertSent(OverdueReminder::class, 1);
    }

    public function test_a_wrong_token_is_refused(): void
    {
        Mail::fake();
        $this->borrowingDue(now()->subDays(2));

        $this->get('/cron/overdue-reminders/wrong-token')->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_the_url_is_disabled_when_no_secret_is_configured(): void
    {
        config(['services.cron.secret' => null]);

        $this->get('/cron/overdue-reminders/anything')->assertNotFound();
    }

    public function test_one_failing_address_does_not_stop_the_other_reminders(): void
    {
        $other = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->borrowingDue(now()->subDays(2));
        Borrowing::create([
            'user_id' => $other->id, 'borrow_date' => now()->subDays(5), 'return_date' => now()->subDay(),
            'status' => 'borrowed', 'claim_code' => 'CHEM-REQ-TEST-' . uniqid(),
        ]);

        $sentTo = [];
        Mail::shouldReceive('to')->twice()->andReturnUsing(function ($email) use (&$sentTo) {
            if (count($sentTo) === 0) {
                $sentTo[] = "failed:$email";
                throw new \RuntimeException('Connection timed out');
            }
            $sentTo[] = $email;
            return new class { public function send($mail) {} };
        });

        $this->artisan('borrowings:send-overdue-reminders')->assertSuccessful();

        $this->assertCount(2, $sentTo);
    }
}
