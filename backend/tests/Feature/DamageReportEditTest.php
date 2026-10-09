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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Editing a damage report: the same form and rules for admin and lab staff,
 * every change recorded (old → new + note), the admin notified of lab staff
 * edits, stock kept in step, and the admin's Damage Report History page.
 */
class DamageReportEditTest extends TestCase
{
    use RefreshDatabase;

    private Campus $carmen;
    private User $admin;
    private User $labStaff;
    private Borrowing $borrowing;
    private EquipmentInventory $beakers;
    private DamageReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->withoutVite();
        $this->carmen = Campus::create(['campus_name' => 'PHINMA COC – Carmen']);
        $location = Location::create(['campus_id' => $this->carmen->id, 'location_name' => 'Lab Storage Room – Carmen']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $this->carmen->id, 'name' => 'Carmen Admin']);
        $this->labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->carmen->id, 'name' => 'Assistant Juan']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $this->carmen->id, 'student_id' => 'S-1', 'course' => 'Nursing']);

        $beaker = Equipment::create(['equipment_code' => 'EQP-1', 'name' => 'Beaker (250 mL)', 'category' => 'Glassware', 'condition' => 'good', 'is_available' => true]);
        // 10 total; 2 returned damaged and held out of stock: 8 available.
        $this->beakers = EquipmentInventory::create(['equipment_id' => $beaker->id, 'campus_id' => $this->carmen->id, 'location_id' => $location->id, 'total_quantity' => 10, 'available_quantity' => 8]);
        $this->borrowing = Borrowing::create(['user_id' => $student->id, 'borrow_date' => now(), 'return_date' => now()->addDay(), 'status' => 'returned', 'claim_code' => 'CHEM-1']);
        BorrowingEquipment::create(['borrowing_id' => $this->borrowing->id, 'equipment_id' => $beaker->id, 'inventory_id' => $this->beakers->id, 'quantity' => 3, 'condition_on_borrow' => 'good']);
        $this->report = DamageReport::create(['borrowing_id' => $this->borrowing->id, 'equipment_id' => $beaker->id, 'equipment_inventory_id' => $this->beakers->id, 'quantity' => 2, 'reported_by' => $this->labStaff->id, 'description' => '2 beakers cracked', 'status' => 'pending']);
    }

    private function edit(User $as, array $overrides = [], ?DamageReport $report = null)
    {
        $report ??= $this->report;
        $prefix = $as->role === 'admin' ? 'admin' : 'lab';

        return $this->actingAs($as)->post("/{$prefix}/damage-reports/{$report->id}/edit", array_merge([
            'description' => 'Only one beaker cracked',
            'quantity' => 1,
            'liable_party' => 'student',
            'liable_reason' => 'Dropped during the experiment',
            'note' => 'Rechecked — only one is cracked.',
        ], $overrides));
    }

    public function test_lab_staff_and_admin_edit_with_the_same_result_and_every_change_is_recorded(): void
    {
        foreach ([$this->labStaff, $this->admin] as $i => $user) {
            $quantity = $i === 0 ? 1 : 2;
            $this->edit($user, ['quantity' => $quantity, 'description' => "Edit by {$user->role}"])->assertSessionHasNoErrors()->assertSessionHas('success');

            $report = $this->report->fresh();
            $this->assertSame("Edit by {$user->role}", $report->description);
            $this->assertSame($quantity, $report->quantity);
            $this->assertSame('student', $report->liable_party);
            $this->assertSame('Dropped during the experiment', $report->liable_reason);
        }

        $logs = AuditLog::where('table_affected', 'damage_reports')->where('record_id', $this->report->id)->orderBy('id')->get();
        $this->assertSame(['Edited Damage Report (Lab Staff)', 'Edited Damage Report'], $logs->pluck('action')->all());
        $this->assertSame(['from' => 2, 'to' => 1], $logs[0]->new_values['changes']['quantity']);
        $this->assertSame('Rechecked — only one is cracked.', $logs[0]->new_values['note']);
    }

    public function test_the_admin_is_notified_only_when_lab_staff_edit(): void
    {
        $this->edit($this->labStaff)->assertSessionHas('success');
        $note = UserNotification::where('user_id', $this->admin->id)->sole();
        $this->assertStringContainsString('Assistant Juan', $note->message);
        $this->assertStringContainsString('Rechecked', $note->message);

        UserNotification::query()->delete();
        $this->edit($this->admin, ['quantity' => 2])->assertSessionHas('success');
        $this->assertSame(0, UserNotification::count());
    }

    public function test_stock_follows_the_new_quantity_on_a_returned_borrowing(): void
    {
        $this->edit($this->labStaff, ['quantity' => 1])->assertSessionHasNoErrors();
        $this->assertSame(9, $this->beakers->fresh()->available_quantity, '2 → 1 puts one unit back.');

        $this->edit($this->labStaff, ['quantity' => 3])->assertSessionHasNoErrors();
        $this->assertSame(7, $this->beakers->fresh()->available_quantity, '1 → 3 holds two more out.');
    }

    public function test_the_rules_are_the_same_for_both_roles(): void
    {
        foreach ([$this->labStaff, $this->admin] as $user) {
            $this->edit($user, ['note' => ''])->assertSessionHasErrors('note');
            $this->edit($user, ['quantity' => 4])->assertSessionHasErrors('quantity');
            $this->edit($user, ['quantity' => 0])->assertSessionHasErrors('quantity');
            $this->edit($user, ['liable_party' => 'nobody'])->assertSessionHasErrors('liable_party');
            $this->edit($user, ['description' => '2 beakers cracked', 'quantity' => 2, 'liable_party' => null, 'liable_reason' => null])->assertSessionHasErrors('note');
        }
        $this->assertSame('2 beakers cracked', $this->report->fresh()->description);
        $this->assertSame(8, $this->beakers->fresh()->available_quantity);

        foreach (['resolved', 'rejected'] as $status) {
            $this->report->update(['status' => $status]);
            $this->edit($this->labStaff)->assertSessionHasErrors('note');
            $this->edit($this->admin)->assertSessionHasErrors('note');
        }
    }

    public function test_the_photo_can_be_replaced(): void
    {
        // A real PNG file (the server checks the actual file content).
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $this->edit($this->admin, ['photo' => new UploadedFile($path, 'crack.png', null, null, true)])->assertSessionHasNoErrors();
        $path = $this->report->fresh()->photo_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_other_campuses_cannot_edit(): void
    {
        $puerto = Campus::create(['campus_name' => 'PHINMA COC – Puerto']);
        $puertoStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $puerto->id]);
        $puertoAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $puerto->id]);

        $this->edit($puertoStaff)->assertForbidden();
        $this->edit($puertoAdmin)->assertForbidden();
        $this->assertSame('2 beakers cracked', $this->report->fresh()->description);
    }

    public function test_the_admin_damage_report_history_lists_this_campus_actions(): void
    {
        $this->edit($this->labStaff)->assertSessionHas('success');
        $this->actingAs($this->labStaff)->post("/lab/damage-reports/{$this->report->id}/reject", ['reason' => 'Duplicate report'])->assertSessionHas('success');

        $this->actingAs($this->admin)->get('/admin/damage-reports/history')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('entries.data', 2)
                ->where('entries.data.0.action', 'Rejected Damage Report (Lab Staff)')
                ->where('entries.data.0.reason', 'Duplicate report')
                ->where('entries.data.1.action', 'Edited Damage Report (Lab Staff)')
                ->where('entries.data.1.user', 'Assistant Juan')
                ->where('entries.data.1.equipment', 'Beaker (250 mL)')
                ->where('entries.data.1.changes.0.field', 'Description'));

        $this->actingAs($this->admin)->get('/admin/damage-reports/history?action=Edited')
            ->assertInertia(fn ($page) => $page->has('entries.data', 1));

        // Another campus's admin sees none of Carmen's history; lab staff can't open it.
        $puerto = Campus::create(['campus_name' => 'PHINMA COC – Puerto']);
        $puertoAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $puerto->id]);
        $this->actingAs($puertoAdmin)->get('/admin/damage-reports/history')->assertInertia(fn ($page) => $page->has('entries.data', 0));
        $this->actingAs($this->labStaff)->get('/admin/damage-reports/history')->assertForbidden();
    }

    public function test_the_report_page_decision_history_shows_the_changes(): void
    {
        $this->edit($this->labStaff)->assertSessionHas('success');

        $this->actingAs($this->admin)->get("/admin/damage-reports/{$this->report->id}")
            ->assertInertia(fn ($page) => $page
                ->where('history.0.action', 'Edited Damage Report (Lab Staff)')
                ->where('history.0.note', 'Rechecked — only one is cracked.')
                ->has('history.0.changes', 4));
    }
}
