<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Campus;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin damage history per student / faculty member, with department/course:
 * a list of people with damage reports at the admin's campus, and each
 * person's reports (as borrower or faculty in-charge).
 */
class DamageHistoryByPersonTest extends TestCase
{
    use RefreshDatabase;

    private Campus $carmen;
    private User $admin;
    private User $student;
    private User $faculty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->carmen = Campus::create(['campus_name' => 'PHINMA COC – Carmen']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $this->carmen->id]);
        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $this->carmen->id, 'student_id' => '02-1234', 'course' => 'Civil Engineering', 'name' => 'Vincent Quinto']);
        $this->faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->carmen->id, 'department' => 'Civil Engineering', 'name' => 'Prof. Saliling']);

        $this->report($this->carmen, $this->student, $this->faculty, 'pending', 2);
        $this->report($this->carmen, $this->student, $this->faculty, 'resolved', 1);
        $this->report($this->carmen, $this->student, null, 'rejected', 1);
    }

    private function report(Campus $campus, User $borrower, ?User $faculty, string $status, int $qty): DamageReport
    {
        $location = Location::firstOrCreate(['campus_id' => $campus->id, 'location_name' => 'Lab Storage Room']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Beaker', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 5, 'available_quantity' => 5]);
        $borrowing = Borrowing::create(['user_id' => $borrower->id, 'faculty_id' => $faculty?->id, 'borrow_date' => now(), 'return_date' => now()->addDay(), 'status' => 'returned', 'claim_code' => 'CHEM-' . uniqid()]);
        BorrowingEquipment::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'inventory_id' => $inventory->id, 'quantity' => $qty, 'condition_on_borrow' => 'good']);

        return DamageReport::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'equipment_inventory_id' => $inventory->id, 'quantity' => $qty, 'reported_by' => $this->admin->id, 'description' => 'Cracked', 'status' => $status]);
    }

    public function test_the_list_shows_each_person_with_department_and_counts(): void
    {
        $this->actingAs($this->admin)->get('/admin/damage-reports/people')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('people', 2)
                ->where('people.0.name', 'Vincent Quinto')
                ->where('people.0.role', 'student')
                ->where('people.0.department', 'Civil Engineering')
                ->where('people.0.id_number', '02-1234')
                ->where('people.0.total', 3)
                ->where('people.0.open', 1)
                ->where('people.0.resolved', 1)
                ->where('people.0.rejected', 1)
                ->where('people.1.name', 'Prof. Saliling')
                ->where('people.1.department', 'Civil Engineering')
                ->where('people.1.total', 2));
    }

    public function test_the_list_can_be_filtered(): void
    {
        $this->actingAs($this->admin)->get('/admin/damage-reports/people?role=faculty')
            ->assertInertia(fn ($page) => $page->has('people', 1)->where('people.0.name', 'Prof. Saliling'));
        $this->actingAs($this->admin)->get('/admin/damage-reports/people?search=02-1234')
            ->assertInertia(fn ($page) => $page->has('people', 1)->where('people.0.name', 'Vincent Quinto'));
        $this->actingAs($this->admin)->get('/admin/damage-reports/people?department=Nursing')
            ->assertInertia(fn ($page) => $page->has('people', 0));
    }

    public function test_one_persons_history_lists_their_reports(): void
    {
        $this->actingAs($this->admin)->get("/admin/damage-reports/people/{$this->student->id}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('person.department', 'Civil Engineering')
                ->has('reports', 3)
                ->where('reports.0.involvement', 'Borrower')
                ->where('summary.total', 3)
                ->where('summary.units', 3)); // the rejected report's unit doesn't count

        $this->actingAs($this->admin)->get("/admin/damage-reports/people/{$this->faculty->id}")
            ->assertInertia(fn ($page) => $page->has('reports', 2)->where('reports.0.involvement', 'Faculty in-charge'));
    }

    public function test_each_campus_admin_sees_only_their_campus_and_lab_staff_cannot_open_it(): void
    {
        $puerto = Campus::create(['campus_name' => 'PHINMA COC – Puerto']);
        $puertoAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $puerto->id]);
        $puertoStudent = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $puerto->id, 'student_id' => 'P-1', 'course' => 'Nursing']);
        $this->report($puerto, $puertoStudent, null, 'pending', 1);

        $this->actingAs($puertoAdmin)->get('/admin/damage-reports/people')
            ->assertInertia(fn ($page) => $page->has('people', 1)->where('people.0.id', $puertoStudent->id));
        $this->actingAs($puertoAdmin)->get("/admin/damage-reports/people/{$this->student->id}")->assertForbidden();
        $this->actingAs($this->admin)->get("/admin/damage-reports/people/{$puertoStudent->id}")->assertForbidden();

        $labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->carmen->id]);
        $this->actingAs($labStaff)->get('/admin/damage-reports/people')->assertForbidden();
    }
}
