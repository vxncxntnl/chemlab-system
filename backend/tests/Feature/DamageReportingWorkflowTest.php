<?php

namespace Tests\Feature;

use App\Mail\BorrowingStatusMail;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Student taps "Report Damage" (notification only) → lab staff check the
 * item and record the damage report for that exact item, with an optional
 * photo → the printed report lists only the damaged item(s).
 */
class DamageReportingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Campus $carmen;
    private User $student;
    private User $labStaff;
    private Borrowing $borrowing;
    private BorrowingEquipment $beakerLine;
    private BorrowingEquipment $burnerLine;
    private EquipmentInventory $beakers;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->withoutVite();
        $this->carmen = Campus::create(['campus_name' => 'Carmen']);
        $location = Location::create(['campus_id' => $this->carmen->id, 'location_name' => 'Chem Lab 01']);
        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $this->carmen->id, 'student_id' => 'S-1', 'course' => 'Nursing', 'name' => 'Juan dela Cruz']);
        $this->labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->carmen->id]);

        $beaker = Equipment::create(['equipment_code' => 'EQP-1', 'name' => 'Beaker (250 mL)', 'category' => 'Glassware', 'condition' => 'good', 'is_available' => true]);
        $burner = Equipment::create(['equipment_code' => 'EQP-2', 'name' => 'Bunsen Burner', 'category' => 'Heating', 'condition' => 'good', 'is_available' => true]);
        $this->beakers = EquipmentInventory::create(['equipment_id' => $beaker->id, 'campus_id' => $this->carmen->id, 'location_id' => $location->id, 'total_quantity' => 10, 'available_quantity' => 8]);
        $burners = EquipmentInventory::create(['equipment_id' => $burner->id, 'campus_id' => $this->carmen->id, 'location_id' => $location->id, 'total_quantity' => 5, 'available_quantity' => 4]);

        $this->borrowing = Borrowing::create(['user_id' => $this->student->id, 'borrow_date' => now(), 'return_date' => now()->addDay(), 'status' => 'borrowed', 'claim_code' => 'CHEM-TEST-1']);
        $this->beakerLine = BorrowingEquipment::create(['borrowing_id' => $this->borrowing->id, 'equipment_id' => $beaker->id, 'inventory_id' => $this->beakers->id, 'quantity' => 2, 'condition_on_borrow' => 'good']);
        $this->burnerLine = BorrowingEquipment::create(['borrowing_id' => $this->borrowing->id, 'equipment_id' => $burner->id, 'inventory_id' => $burners->id, 'quantity' => 1, 'condition_on_borrow' => 'good']);
    }

    private function studentReports(BorrowingEquipment $line, ?string $note = null)
    {
        return $this->actingAs($this->student)->post("/student/borrowings/{$this->borrowing->id}/report-damage", ['borrowing_equipment_id' => $line->id, 'note' => $note]);
    }

    // ── Status tracking ──────────────────────────────────────────────────

    public function test_the_report_is_tracked_on_the_item_with_the_optional_note(): void
    {
        $this->studentReports($this->beakerLine, 'cracked rim')->assertSessionHas('success');

        $line = $this->beakerLine->fresh();
        $this->assertSame('reported', $line->damage_report_status);
        $this->assertNotNull($line->damage_reported_at);
        $this->assertSame('cracked rim', $line->damage_report_note);
        $this->assertNull($this->burnerLine->fresh()->damage_report_status, 'Only the reported item.');
        $this->assertStringContainsString('cracked rim', UserNotification::where('user_id', $this->labStaff->id)->value('message'));
    }

    public function test_the_lab_staff_list_flags_requests_with_a_pending_damage_report(): void
    {
        $this->studentReports($this->beakerLine)->assertSessionHas('success');

        $this->actingAs($this->labStaff)->get('/lab/borrowings')
            ->assertInertia(fn ($page) => $page->where('borrowings.data.0.has_damage_report', true));

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get('/admin/borrowings')
            ->assertInertia(fn ($page) => $page->where('borrowings.data.0.has_damage_report', true));
    }

    public function test_recording_damage_marks_the_item_confirmed(): void
    {
        $this->studentReports($this->beakerLine)->assertSessionHas('success');
        $this->labRecords($this->labStaff, 'lab', ['borrowing_equipment_id' => $this->beakerLine->id, 'quantity' => 1, 'description' => 'Cracked'])->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $this->beakerLine->fresh()->damage_report_status);
        $this->studentReports($this->beakerLine)->assertSessionHas('info'); // can't re-report a confirmed item
    }

    public function test_no_damage_found_closes_the_report_and_notifies_the_student(): void
    {
        $this->studentReports($this->beakerLine)->assertSessionHas('success');

        $this->actingAs($this->labStaff)->post("/lab/borrowings/{$this->borrowing->id}/no-damage", ['borrowing_equipment_id' => $this->beakerLine->id])
            ->assertSessionHas('success');

        $this->assertSame('no_damage', $this->beakerLine->fresh()->damage_report_status);
        $this->assertSame(0, DamageReport::count());
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->student->id, 'title' => 'Equipment Checked — No Damage Found']);

        // Nothing pending anymore, so it can't be dismissed twice…
        $this->actingAs($this->labStaff)->post("/lab/borrowings/{$this->borrowing->id}/no-damage", ['borrowing_equipment_id' => $this->beakerLine->id])
            ->assertSessionHas('error');
        // …but the student can report it again if something new happens.
        $this->studentReports($this->beakerLine)->assertSessionHas('success');
    }

    public function test_no_damage_found_respects_campus_and_works_for_the_admin(): void
    {
        $this->studentReports($this->beakerLine)->assertSessionHas('success');
        $outsider = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => Campus::create(['campus_name' => 'Iligan'])->id]);
        $this->actingAs($outsider)->post("/lab/borrowings/{$this->borrowing->id}/no-damage", ['borrowing_equipment_id' => $this->beakerLine->id])->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->post("/admin/borrowings/{$this->borrowing->id}/no-damage", ['borrowing_equipment_id' => $this->beakerLine->id])
            ->assertSessionHas('success');
        $this->assertSame('no_damage', $this->beakerLine->fresh()->damage_report_status);
    }

    public function test_returning_resolves_pending_reports_from_the_return_condition(): void
    {
        $this->studentReports($this->beakerLine)->assertSessionHas('success');
        $this->studentReports($this->burnerLine)->assertSessionHas('success');

        $this->actingAs($this->labStaff)->post("/lab/borrowings/{$this->borrowing->id}/return", [
            'conditions' => [
                $this->beakerLine->id => ['good' => 1, 'damaged' => 1],
                $this->burnerLine->id => ['good' => 1, 'damaged' => 0],
            ],
        ])->assertSessionHas('success');

        $this->assertSame('confirmed', $this->beakerLine->fresh()->damage_report_status);
        $this->assertSame('no_damage', $this->burnerLine->fresh()->damage_report_status);
    }

    private function labRecords(User $as, string $prefix, array $data)
    {
        return $this->actingAs($as)->post("/{$prefix}/borrowings/{$this->borrowing->id}/damage", $data);
    }

    // ── Student: one tap, notification only ──────────────────────────────

    public function test_report_damage_notifies_the_campus_lab_staff_with_the_item_details(): void
    {
        $otherCampusStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => Campus::create(['campus_name' => 'Iligan'])->id]);

        $this->studentReports($this->beakerLine)->assertSessionHas('success');

        $note = UserNotification::where('user_id', $this->labStaff->id)->first();
        $this->assertNotNull($note);
        $this->assertSame('Possible Damage Reported by Student', $note->title);
        $this->assertStringContainsString('Juan dela Cruz', $note->message);
        $this->assertStringContainsString('Beaker (250 mL)', $note->message);
        $this->assertStringContainsString("#{$this->borrowing->id}", $note->message);
        $this->assertSame("/lab/borrowings/{$this->borrowing->id}", $note->link);
        Mail::assertSent(BorrowingStatusMail::class, fn ($m) => $m->hasTo($this->labStaff->email));
        $this->assertFalse(UserNotification::where('user_id', $otherCampusStaff->id)->exists(), 'Only that campus\'s lab staff.');

        $this->assertSame(0, DamageReport::count(), 'No damage report until lab staff verify it.');
    }

    public function test_tapping_again_does_not_notify_twice(): void
    {
        $this->studentReports($this->beakerLine)->assertSessionHas('success');
        $this->studentReports($this->beakerLine)->assertSessionHas('info');

        $this->assertSame(1, UserNotification::where('user_id', $this->labStaff->id)->count());
    }

    public function test_report_damage_only_works_for_the_students_own_currently_borrowed_items(): void
    {
        $other = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $this->carmen->id, 'student_id' => 'S-2', 'course' => 'Nursing']);
        $this->actingAs($other)->post("/student/borrowings/{$this->borrowing->id}/report-damage", ['borrowing_equipment_id' => $this->beakerLine->id])->assertForbidden();

        $this->studentReports(new BorrowingEquipment(['id' => 99999]))->assertSessionHasErrors('borrowing_equipment_id');

        $this->borrowing->update(['status' => 'returned']);
        $this->studentReports($this->beakerLine)->assertSessionHas('error');
    }

    // ── Lab staff: record the verified report ────────────────────────────

    public function test_lab_staff_record_damage_for_the_exact_item_with_a_photo(): void
    {
        $photo = UploadedFile::fake()->createWithContent('crack.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00");

        $this->labRecords($this->labStaff, 'lab', [
            'borrowing_equipment_id' => $this->beakerLine->id, 'quantity' => 1,
            'description' => 'Crack along the base, verified with the student.', 'photo' => $photo,
        ])->assertSessionHas('success');

        $report = DamageReport::sole();
        $this->assertSame($this->borrowing->id, $report->borrowing_id);
        $this->assertSame($this->beakerLine->equipment_id, $report->equipment_id);
        $this->assertSame($this->beakers->id, $report->equipment_inventory_id);
        $this->assertSame(1, $report->quantity);
        $this->assertSame($this->labStaff->id, $report->reported_by);
        $this->assertSame('pending', $report->status);
        $this->assertNotNull($report->photo_path);
        Storage::disk('local')->assertExists($report->photo_path);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->labStaff->id, 'action' => 'Recorded Damage Report']);
    }

    public function test_the_photo_is_optional_and_quantity_is_capped(): void
    {
        $this->labRecords($this->labStaff, 'lab', ['borrowing_equipment_id' => $this->beakerLine->id, 'quantity' => 3, 'description' => 'x'])
            ->assertSessionHasErrors('quantity');
        $this->labRecords($this->labStaff, 'lab', ['borrowing_equipment_id' => $this->beakerLine->id, 'quantity' => 2, 'description' => 'Both cracked'])
            ->assertSessionHasNoErrors();
        $this->labRecords($this->labStaff, 'lab', ['borrowing_equipment_id' => $this->beakerLine->id, 'quantity' => 1, 'description' => 'Again'])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(1, DamageReport::count());
    }

    public function test_lab_staff_from_another_campus_cannot_record_damage_but_the_admin_can(): void
    {
        $outsider = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => Campus::create(['campus_name' => 'Iligan'])->id]);
        $this->labRecords($outsider, 'lab', ['borrowing_equipment_id' => $this->beakerLine->id, 'quantity' => 1, 'description' => 'x'])->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->labRecords($admin, 'admin', ['borrowing_equipment_id' => $this->beakerLine->id, 'quantity' => 1, 'description' => 'Recorded by admin'])
            ->assertSessionHas('success');
        $this->assertSame(1, DamageReport::count());
    }

    public function test_students_cannot_record_damage_reports_themselves(): void
    {
        $this->labRecords($this->student, 'lab', ['borrowing_equipment_id' => $this->beakerLine->id, 'quantity' => 1, 'description' => 'x'])->assertForbidden();
        $this->assertSame(0, DamageReport::count());
    }

    // ── Return afterwards + inventory ────────────────────────────────────

    public function test_returning_after_recording_damage_does_not_duplicate_the_report_and_keeps_stock_right(): void
    {
        $this->labRecords($this->labStaff, 'lab', ['borrowing_equipment_id' => $this->beakerLine->id, 'quantity' => 1, 'description' => 'Cracked'])->assertSessionHasNoErrors();

        $this->actingAs($this->labStaff)->post("/lab/borrowings/{$this->borrowing->id}/return", [
            'conditions' => [
                $this->beakerLine->id => ['good' => 1, 'damaged' => 1],
                $this->burnerLine->id => ['good' => 1, 'damaged' => 0],
            ],
            'liable_party' => 'student', 'liable_reason' => 'Dropped it.',
        ])->assertSessionHas('success');

        $this->assertSame(1, DamageReport::count(), 'The report recorded earlier is reused, not duplicated.');
        $this->assertSame('student', DamageReport::sole()->liable_party);
        $this->assertSame(9, $this->beakers->fresh()->available_quantity, 'Only the good beaker goes back to stock.');
        $this->assertSame('damaged', $this->beakerLine->fresh()->condition_on_return);
        $this->assertSame('good', $this->burnerLine->fresh()->condition_on_return);
    }

    // ── Printed report ───────────────────────────────────────────────────

    public function test_the_printed_report_lists_only_the_damaged_item(): void
    {
        $this->labRecords($this->labStaff, 'lab', ['borrowing_equipment_id' => $this->beakerLine->id, 'quantity' => 1, 'description' => 'Cracked'])->assertSessionHasNoErrors();
        $report = DamageReport::sole();

        $this->actingAs($this->labStaff)->get("/lab/damage-reports/{$report->id}/print")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('report.borrowing.borrowing_equipment', 2)   // the borrowing has 2 items…
                ->has('report.borrowing.damage_reports', 1)        // …but only 1 was damaged
                ->where('report.borrowing.damage_reports.0.equipment.name', 'Beaker (250 mL)')
                ->where('report.borrowing.damage_reports.0.quantity', 1)
                ->where('report.borrowing.damage_reports.0.inventory.location.location_name', 'Chem Lab 01'));
    }

    public function test_two_damaged_items_both_appear_on_the_printed_report(): void
    {
        $this->labRecords($this->labStaff, 'lab', ['borrowing_equipment_id' => $this->beakerLine->id, 'quantity' => 1, 'description' => 'Cracked'])->assertSessionHasNoErrors();
        $this->labRecords($this->labStaff, 'lab', ['borrowing_equipment_id' => $this->burnerLine->id, 'quantity' => 1, 'description' => 'Bent valve'])->assertSessionHasNoErrors();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get('/admin/damage-reports/' . DamageReport::first()->id . '/print')
            ->assertInertia(fn ($page) => $page->has('report.borrowing.damage_reports', 2));
    }
}
