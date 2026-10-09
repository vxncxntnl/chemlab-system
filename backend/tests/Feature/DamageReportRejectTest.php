<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Campus;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Admin and lab staff can reject a damage report (no damage), with a reason.
 * A lab staff rejection notifies the admins, who can reopen it within 7 days.
 * Rejecting returns held-out stock; reopening takes it back.
 */
class DamageReportRejectTest extends TestCase
{
    use RefreshDatabase;

    private Campus $carmen;
    private User $admin;
    private User $labStaff;
    private Borrowing $borrowing;
    private BorrowingEquipment $line;
    private EquipmentInventory $beakers;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutVite();
        $this->carmen = Campus::create(['campus_name' => 'Carmen']);
        $location = Location::create(['campus_id' => $this->carmen->id, 'location_name' => 'Chem Lab 01']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $this->carmen->id, 'student_id' => 'S-1', 'course' => 'Nursing']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'name' => 'Admin One']);
        $this->labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->carmen->id, 'name' => 'Assistant Juan']);

        $beaker = Equipment::create(['equipment_code' => 'EQP-1', 'name' => 'Beaker (250 mL)', 'category' => 'Glassware', 'condition' => 'good', 'is_available' => true]);
        // 10 total; 2 were returned damaged, so they are held out: 8 available.
        $this->beakers = EquipmentInventory::create(['equipment_id' => $beaker->id, 'campus_id' => $this->carmen->id, 'location_id' => $location->id, 'total_quantity' => 10, 'available_quantity' => 8]);

        $this->borrowing = Borrowing::create(['user_id' => $student->id, 'borrow_date' => now(), 'return_date' => now()->addDay(), 'status' => 'returned', 'claim_code' => 'CHEM-TEST-1']);
        $this->line = BorrowingEquipment::create(['borrowing_id' => $this->borrowing->id, 'equipment_id' => $beaker->id, 'inventory_id' => $this->beakers->id, 'quantity' => 2, 'condition_on_borrow' => 'good']);
    }

    private function report(string $status = 'pending', int $qty = 2): DamageReport
    {
        return DamageReport::create([
            'borrowing_id' => $this->borrowing->id,
            'equipment_id' => $this->line->equipment_id,
            'equipment_inventory_id' => $this->beakers->id,
            'quantity' => $qty,
            'reported_by' => $this->labStaff->id,
            'description' => 'Cracked rim',
            'status' => $status,
        ]);
    }

    private function reject(User $as, DamageReport $report, array $data = ['reason' => 'No damage found after inspection'])
    {
        $prefix = $as->role === 'admin' ? 'admin' : 'lab';
        return $this->actingAs($as)->post("/{$prefix}/damage-reports/{$report->id}/reject", $data);
    }

    public function test_lab_staff_can_reject_with_a_reason_and_the_stock_goes_back(): void
    {
        $report = $this->report('assessed');

        $this->reject($this->labStaff, $report, ['reason' => 'No damage found after inspection', 'details' => 'no crack, residue only'])
            ->assertSessionHas('success');

        $report->refresh();
        $this->assertSame('rejected', $report->status);
        $this->assertSame('No damage found after inspection — no crack, residue only', $report->rejection_reason);
        $this->assertSame($this->labStaff->id, $report->rejected_by);
        $this->assertNotNull($report->rejected_at);
        $this->assertSame('assessed', $report->status_before_rejection);
        $this->assertSame(10, $this->beakers->fresh()->available_quantity, 'The 2 held-out units are available again.');
        $this->assertSame(2, $report->rejection_restocked_qty);
        $this->assertTrue(AuditLog::where('action', 'Rejected Damage Report (Lab Staff)')->where('record_id', $report->id)->where('user_id', $this->labStaff->id)->exists());
    }

    public function test_a_lab_staff_rejection_notifies_the_admins_but_an_admin_rejection_does_not(): void
    {
        $this->reject($this->labStaff, $this->report())->assertSessionHas('success');

        $note = UserNotification::where('user_id', $this->admin->id)->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('Assistant Juan', $note->message);
        $this->assertStringContainsString('No damage found after inspection', $note->message);
        $this->assertStringContainsString('7 days', $note->message);

        UserNotification::query()->delete();
        $this->reject($this->admin, $this->report())->assertSessionHas('success');
        $this->assertSame(0, UserNotification::count());
    }

    public function test_rejecting_while_still_borrowed_changes_no_stock(): void
    {
        $this->borrowing->update(['status' => 'borrowed']);
        $report = $this->report();

        $this->reject($this->admin, $report)->assertSessionHas('success');

        $this->assertSame('rejected', $report->fresh()->status);
        $this->assertSame(8, $this->beakers->fresh()->available_quantity);
        $this->assertSame(0, $report->fresh()->rejection_restocked_qty);
    }

    public function test_a_reason_is_required_and_other_needs_details(): void
    {
        $report = $this->report();

        $this->reject($this->labStaff, $report, [])->assertSessionHasErrors('reason');
        $this->reject($this->labStaff, $report, ['reason' => 'Made up reason'])->assertSessionHasErrors('reason');
        $this->reject($this->labStaff, $report, ['reason' => 'Other'])->assertSessionHasErrors('details');
        $this->assertSame('pending', $report->fresh()->status);

        $this->reject($this->labStaff, $report, ['reason' => 'Other', 'details' => 'Wrong student'])->assertSessionHas('success');
        $this->assertSame('Wrong student', $report->fresh()->rejection_reason);
    }

    public function test_a_resolved_report_cannot_be_rejected(): void
    {
        $report = $this->report('resolved');

        $this->reject($this->admin, $report)->assertSessionHasErrors('reason');
        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertSame(8, $this->beakers->fresh()->available_quantity);
    }

    public function test_lab_staff_cannot_reject_another_campus_report_or_reopen(): void
    {
        $otherCampus = Campus::create(['campus_name' => 'Iligan']);
        $outsider = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $otherCampus->id]);
        $report = $this->report();

        $this->reject($outsider, $report)->assertForbidden();
        $this->assertSame('pending', $report->fresh()->status);

        $this->reject($this->labStaff, $report)->assertSessionHas('success');
        $this->actingAs($this->labStaff)->post("/admin/damage-reports/{$report->id}/reopen")->assertForbidden();
        $this->assertSame('rejected', $report->fresh()->status);
    }

    public function test_admin_can_reopen_within_7_days_and_the_stock_is_taken_back(): void
    {
        $report = $this->report('assessed');
        $this->reject($this->labStaff, $report)->assertSessionHas('success');
        $this->assertSame(10, $this->beakers->fresh()->available_quantity);

        $this->travel(6)->days();
        $this->actingAs($this->admin)->post("/admin/damage-reports/{$report->id}/reopen")->assertSessionHas('success');

        $report->refresh();
        $this->assertSame('assessed', $report->status, 'Back to where it was.');
        $this->assertNull($report->rejection_reason);
        $this->assertNull($report->rejected_by);
        $this->assertSame(8, $this->beakers->fresh()->available_quantity);
        $this->assertTrue(AuditLog::where('action', 'Reopened Damage Report')->where('record_id', $report->id)->where('user_id', $this->admin->id)->exists());
    }

    public function test_reopen_is_refused_after_7_days(): void
    {
        $report = $this->report();
        $this->reject($this->labStaff, $report)->assertSessionHas('success');

        $this->travel(8)->days();
        $this->actingAs($this->admin)->post("/admin/damage-reports/{$report->id}/reopen")->assertSessionHas('error');

        $this->assertSame('rejected', $report->fresh()->status);
        $this->assertSame(10, $this->beakers->fresh()->available_quantity);
    }

    public function test_reopen_is_refused_when_the_returned_units_are_lent_out_again(): void
    {
        $report = $this->report();
        $this->reject($this->labStaff, $report)->assertSessionHas('success');
        $this->beakers->update(['available_quantity' => 1]);

        $this->actingAs($this->admin)->post("/admin/damage-reports/{$report->id}/reopen")->assertSessionHas('error');

        $this->assertSame('rejected', $report->fresh()->status);
        $this->assertSame(1, $this->beakers->fresh()->available_quantity);
    }

    public function test_a_rejected_report_cannot_be_assessed_or_resolved_so_stock_is_not_added_twice(): void
    {
        $report = $this->report();
        $this->reject($this->labStaff, $report)->assertSessionHas('success');

        foreach (['admin' => $this->admin, 'lab' => $this->labStaff] as $prefix => $user) {
            $this->actingAs($user)->post("/{$prefix}/damage-reports/{$report->id}/resolve")->assertSessionHas('error');
            $this->actingAs($user)->post("/{$prefix}/damage-reports/{$report->id}/assess")->assertSessionHas('error');
        }

        $this->assertSame('rejected', $report->fresh()->status);
        $this->assertSame(10, $this->beakers->fresh()->available_quantity);
    }

    public function test_after_a_rejection_real_damage_can_still_be_recorded_for_the_item(): void
    {
        $this->borrowing->update(['status' => 'borrowed']);
        $this->reject($this->labStaff, $this->report())->assertSessionHas('success');

        $this->actingAs($this->labStaff)->post("/lab/borrowings/{$this->borrowing->id}/damage", [
            'borrowing_equipment_id' => $this->line->id,
            'quantity' => 2,
            'description' => 'Found a real crack later',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, DamageReport::where('status', 'pending')->count());
    }

    public function test_the_report_page_shows_who_decided_what(): void
    {
        $report = $this->report();
        $this->reject($this->labStaff, $report)->assertSessionHas('success');

        $this->actingAs($this->admin)->get("/admin/damage-reports/{$report->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.status', 'rejected')
                ->where('report.rejecter.name', 'Assistant Juan')
                ->where('canReopen', true)
                ->where('history.0.action', 'Rejected Damage Report (Lab Staff)')
                ->where('history.0.user', 'Assistant Juan')
                ->where('history.0.reason', 'No damage found after inspection'));

        $this->actingAs($this->labStaff)->get("/lab/damage-reports/{$report->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('history.0.user', 'Assistant Juan'));
    }
}
