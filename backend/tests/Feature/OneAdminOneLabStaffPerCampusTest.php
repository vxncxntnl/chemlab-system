<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Campus;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\LabInventory;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * One admin for the whole system, one active lab staff per campus (Carmen,
 * Puerto, Iligan), and each lab staff account confined to its own campus —
 * enforced on the backend even for hand-crafted requests.
 */
class OneAdminOneLabStaffPerCampusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    /** @var array<string, Campus> */
    private array $campus = [];
    /** @var array<string, User> */
    private array $staff = [];
    /** @var array<string, array{inventory: EquipmentInventory, borrowing: Borrowing, report: DamageReport, log: MaintenanceLog}> */
    private array $records = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        foreach (['Carmen', 'Puerto', 'Iligan'] as $name) {
            $campus = Campus::create(['campus_name' => "PHINMA COC – {$name}"]);
            $this->campus[$name] = $campus;
            $this->staff[$name] = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $campus->id, 'name' => "{$name} Staff"]);
            $this->records[$name] = $this->recordsAt($campus, $name);
        }
    }

    private function recordsAt(Campus $campus, string $name): array
    {
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => "{$name} Lab"]);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . $name, 'name' => "{$name} Beaker", 'category' => 'Glassware', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 5, 'available_quantity' => 4]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $campus->id, 'student_id' => 'S-' . $name, 'course' => 'Nursing']);
        $borrowing = Borrowing::create(['user_id' => $student->id, 'borrow_date' => now(), 'return_date' => now()->addDays(3), 'status' => 'borrowed', 'claim_code' => 'CHEM-' . $name]);
        BorrowingEquipment::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'inventory_id' => $inventory->id, 'quantity' => 1, 'condition_on_borrow' => 'good']);
        $report = DamageReport::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'equipment_inventory_id' => $inventory->id, 'quantity' => 1, 'reported_by' => $this->staff[$name]->id, 'description' => 'Crack', 'status' => 'pending']);
        $log = MaintenanceLog::create(['equipment_id' => $equipment->id, 'performed_by' => $this->staff[$name]->id, 'type' => 'inspection', 'description' => 'Check', 'maintenance_date' => now(), 'status' => 'completed']);

        return compact('inventory', 'borrowing', 'report', 'log');
    }

    private function userPayload(array $overrides): array
    {
        return array_merge([
            'name' => 'New Person',
            'email' => 'new.' . uniqid() . '@example.test',
            'password' => 'Str0ng!Passw0rd#',
            'password_confirmation' => 'Str0ng!Passw0rd#',
        ], $overrides);
    }

    // ── One admin ───────────────────────────────────────────────────────

    public function test_a_second_admin_cannot_be_created_or_promoted(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', $this->userPayload(['role' => 'admin']))
            ->assertSessionHasErrors('role');

        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus['Carmen']->id, 'department' => 'Nursing']);
        $this->actingAs($this->admin)->put("/admin/users/{$faculty->id}", ['name' => $faculty->name, 'email' => $faculty->email, 'role' => 'admin'])
            ->assertSessionHasErrors('role');

        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertSame('faculty', $faculty->fresh()->role);
    }

    public function test_the_database_also_refuses_a_second_admin(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_the_admin_can_still_edit_their_own_account(): void
    {
        $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", ['name' => 'Renamed Admin', 'email' => $this->admin->email, 'role' => 'admin'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed Admin', $this->admin->fresh()->name);
    }

    public function test_only_an_admin_can_reach_user_management(): void
    {
        $this->actingAs($this->staff['Carmen'])->post('/admin/users', $this->userPayload(['role' => 'admin']))->assertForbidden();
        $this->actingAs($this->staff['Carmen'])->put("/admin/users/{$this->staff['Carmen']->id}", ['role' => 'admin'])->assertForbidden();
        $this->assertSame('lab_staff', $this->staff['Carmen']->fresh()->role);
    }

    public function test_the_user_form_shows_which_roles_and_campuses_are_taken(): void
    {
        $this->actingAs($this->admin)->get('/admin/users/create')
            ->assertInertia(fn ($page) => $page
                ->where('existingAdmin.email', $this->admin->email)
                ->where('labStaffByCampus.' . $this->campus['Puerto']->id, 'Puerto Staff'));
    }

    // ── One active lab staff per campus ─────────────────────────────────

    public function test_each_campus_has_its_own_lab_staff_and_a_second_one_is_refused(): void
    {
        foreach ($this->campus as $name => $campus) {
            $this->actingAs($this->admin)->post('/admin/users', $this->userPayload(['role' => 'lab_staff', 'campus_id' => $campus->id]))
                ->assertSessionHasErrors('campus_id');
            $this->assertSame(1, User::where('role', 'lab_staff')->where('campus_id', $campus->id)->count(), $name);
        }
    }

    public function test_lab_staff_cannot_be_moved_or_promoted_into_a_campus_that_has_one(): void
    {
        $this->actingAs($this->admin)->put("/admin/users/{$this->staff['Iligan']->id}", [
            'name' => 'Iligan Staff', 'email' => $this->staff['Iligan']->email, 'role' => 'lab_staff', 'campus_id' => $this->campus['Carmen']->id,
        ])->assertSessionHasErrors('campus_id');
        $this->assertSame($this->campus['Iligan']->id, $this->staff['Iligan']->fresh()->campus_id);

        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus['Puerto']->id, 'department' => 'Nursing']);
        $this->actingAs($this->admin)->put("/admin/users/{$faculty->id}", [
            'name' => $faculty->name, 'email' => $faculty->email, 'role' => 'lab_staff', 'campus_id' => $this->campus['Puerto']->id,
        ])->assertSessionHasErrors('campus_id');
        $this->assertSame('faculty', $faculty->fresh()->role);
    }

    public function test_a_suspended_lab_staff_can_be_replaced_but_not_reactivated_alongside_the_new_one(): void
    {
        $old = $this->staff['Carmen'];
        $this->actingAs($this->admin)->post("/admin/users/{$old->id}/suspend")->assertSessionHas('success');

        $this->actingAs($this->admin)->post('/admin/users', $this->userPayload(['role' => 'lab_staff', 'campus_id' => $this->campus['Carmen']->id, 'email' => 'replacement@example.test']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post("/admin/users/{$old->id}/approve")->assertSessionHas('error');
        $this->assertSame('suspended', $old->fresh()->status);
    }

    public function test_the_database_also_refuses_a_second_active_lab_staff_for_a_campus(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->campus['Iligan']->id]);
    }

    // ── No changing your own role or campus ─────────────────────────────

    public function test_signed_in_users_cannot_change_their_own_campus_or_role_through_complete_profile(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $this->campus['Carmen']->id, 'student_id' => 'S-X', 'course' => 'Nursing']);
        $this->actingAs($student)->post('/complete-profile', ['role' => 'student', 'campus_id' => $this->campus['Iligan']->id, 'student_id' => 'S-X', 'course' => 'Nursing'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame($this->campus['Carmen']->id, $student->fresh()->campus_id);

        $staff = $this->staff['Puerto'];
        $this->actingAs($staff)->post('/complete-profile', ['role' => 'student', 'campus_id' => $this->campus['Carmen']->id, 'student_id' => 'S-Y', 'course' => 'Nursing'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame('lab_staff', $staff->fresh()->role);
        $this->assertSame($this->campus['Puerto']->id, $staff->fresh()->campus_id);

        $this->actingAs($this->admin)->post('/complete-profile', ['role' => 'faculty', 'campus_id' => $this->campus['Carmen']->id, 'department' => 'Nursing']);
        $this->assertSame('admin', $this->admin->fresh()->role);
    }

    public function test_a_new_sign_up_can_still_complete_their_profile(): void
    {
        $new = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => null]);

        $this->actingAs($new)->post('/complete-profile', ['role' => 'student', 'campus_id' => $this->campus['Puerto']->id, 'student_id' => 'S-NEW', 'course' => 'Nursing'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame($this->campus['Puerto']->id, $new->fresh()->campus_id);
    }

    // ── Lab staff see and manage only their own campus ──────────────────

    public function test_each_lab_staff_sees_only_their_own_campus_in_lists_and_dashboard(): void
    {
        foreach ($this->staff as $name => $staff) {
            $own = $this->records[$name];

            $this->actingAs($staff)->get('/lab/equipment')->assertOk()
                ->assertInertia(fn ($page) => $page->has('inventory.data', 1)->where('inventory.data.0.id', $own['inventory']->id));
            $this->actingAs($staff)->get('/lab/borrowings')->assertOk()
                ->assertInertia(fn ($page) => $page->has('borrowings.data', 1)->where('borrowings.data.0.id', $own['borrowing']->id));
            $this->actingAs($staff)->get('/lab/damage-reports')->assertOk()
                ->assertInertia(fn ($page) => $page->has('reports.data', 1)->where('reports.data.0.id', $own['report']->id));
            $this->actingAs($staff)->get('/lab/maintenance')->assertOk()
                ->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('logs.data.0.id', $own['log']->id));
            $this->actingAs($staff)->get('/lab/dashboard')->assertOk()
                ->assertInertia(fn ($page) => $page->where('stats.total_equipment', 5)->where('stats.borrowed', 1)->where('stats.damaged', 1));
        }
    }

    public function test_cross_campus_urls_ids_and_form_values_are_refused(): void
    {
        foreach ($this->staff as $name => $staff) {
            foreach ($this->records as $otherName => $other) {
                if ($otherName === $name) {
                    continue;
                }
                $b = $other['borrowing']->id;
                $r = $other['report']->id;
                $line = $other['borrowing']->borrowingEquipment()->first();
                $label = "{$name} staff -> {$otherName} record";

                $this->actingAs($staff)->get("/lab/borrowings/{$b}")->assertForbidden();
                $this->actingAs($staff)->post("/lab/borrowings/{$b}/return", [])->assertForbidden();
                $this->actingAs($staff)->post("/lab/borrowings/{$b}/release", [])->assertForbidden();
                $this->actingAs($staff)->post("/lab/borrowings/{$b}/damage", ['borrowing_equipment_id' => $line->id, 'quantity' => 1, 'description' => 'x'])->assertForbidden();
                $this->actingAs($staff)->post("/lab/borrowings/{$b}/no-damage", ['borrowing_equipment_id' => $line->id])->assertForbidden();
                $this->actingAs($staff)->get("/lab/damage-reports/{$r}")->assertForbidden();
                $this->actingAs($staff)->get("/lab/damage-reports/{$r}/print")->assertForbidden();
                $this->actingAs($staff)->get("/lab/damage-reports/{$r}/photo")->assertForbidden();
                $this->actingAs($staff)->post("/lab/damage-reports/{$r}/assess")->assertForbidden();
                $this->actingAs($staff)->post("/lab/damage-reports/{$r}/resolve")->assertForbidden();
                $this->actingAs($staff)->post("/lab/damage-reports/{$r}/reject", ['reason' => 'Duplicate report'])->assertForbidden();
                $this->actingAs($staff)->get("/lab/maintenance/{$other['log']->id}")->assertForbidden();
                $this->actingAs($staff)->post('/lab/maintenance', [
                    'equipment_id' => $other['inventory']->equipment_id, 'type' => 'repair', 'description' => 'x',
                    'maintenance_date' => now()->toDateString(), 'status' => 'completed',
                ])->assertSessionHasErrors('equipment_id');

                // Walk-in borrowing with another campus's stock.
                $this->actingAs($staff)->post('/lab/borrowings', [
                    'borrow_date' => now()->toDateString(), 'return_date' => now()->addDay()->toDateString(),
                    'equipment' => [['equipment_id' => $other['inventory']->equipment_id, 'inventory_id' => $other['inventory']->id, 'quantity' => 1]],
                ])->assertSessionHasErrors('equipment.0.inventory_id');
                $this->actingAs($staff)->get("/lab/borrowings/create?inventory_id={$other['inventory']->id}")
                    ->assertInertia(fn ($page) => $page->where('preselectedInventory', null));

                $this->assertSame('borrowed', $other['borrowing']->fresh()->status, $label);
                $this->assertSame('pending', $other['report']->fresh()->status, $label);
            }
        }

        $this->assertSame(3, Borrowing::count(), 'No cross-campus walk-in borrowing was created.');
    }

    public function test_only_carmen_lab_staff_can_open_the_lab_inventory_masterfile(): void
    {
        $item = LabInventory::create(['lab' => 'chemistry', 'category' => 'general', 'description' => 'Beaker', 'quantity' => 10]);

        $this->actingAs($this->staff['Carmen'])->get('/lab/lab-inventory')->assertOk();
        $this->actingAs($this->staff['Carmen'])->put("/lab/lab-inventory/{$item->id}", ['quantity' => 9])->assertRedirect();
        $this->assertSame(9, $item->fresh()->quantity);

        foreach (['Puerto', 'Iligan'] as $name) {
            $this->actingAs($this->staff[$name])->get('/lab/lab-inventory')->assertForbidden();
            $this->actingAs($this->staff[$name])->put("/lab/lab-inventory/{$item->id}", ['quantity' => 0])->assertForbidden();
        }
        $this->assertSame(9, $item->fresh()->quantity);
    }

    public function test_lab_staff_still_work_normally_on_their_own_campus(): void
    {
        $own = $this->records['Puerto'];
        $line = $own['borrowing']->borrowingEquipment()->first();

        $this->actingAs($this->staff['Puerto'])->get("/lab/borrowings/{$own['borrowing']->id}")->assertOk();
        $this->actingAs($this->staff['Puerto'])->get("/lab/damage-reports/{$own['report']->id}")->assertOk();
        $this->actingAs($this->staff['Puerto'])->post("/lab/borrowings/{$own['borrowing']->id}/return", [
            'conditions' => [$line->id => ['good' => 1, 'damaged' => 0]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('returned', $own['borrowing']->fresh()->status);
    }

    // ── The admin sees every campus ─────────────────────────────────────

    public function test_the_admin_can_open_records_from_all_three_campuses(): void
    {
        $this->actingAs($this->admin)->get('/admin/borrowings')->assertOk();
        $this->actingAs($this->admin)->get('/admin/damage-reports')->assertOk()
            ->assertInertia(fn ($page) => $page->has('reports.data', 3));

        foreach ($this->records as $name => $r) {
            $this->actingAs($this->admin)->get("/admin/borrowings/{$r['borrowing']->id}")->assertOk();
            $this->actingAs($this->admin)->get("/admin/equipment/{$r['inventory']->equipment_id}")->assertOk();
            $this->actingAs($this->admin)->get("/admin/damage-reports/{$r['report']->id}")->assertOk();
            $this->actingAs($this->admin)->get("/admin/maintenance/{$r['log']->id}")->assertOk();
        }
        $this->actingAs($this->admin)->get('/admin/lab-inventory')->assertOk();
    }
}
