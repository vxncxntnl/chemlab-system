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
 * Damage report details show the borrower's and faculty in-charge's email,
 * campus and course/department — on the admin and lab staff pages alike —
 * from each person's own role field, with missing values left empty
 * ("Not provided" on the page).
 */
class DamageReportAffiliationTest extends TestCase
{
    use RefreshDatabase;

    private Campus $carmen;
    private User $admin;
    private User $labStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->carmen = Campus::create(['campus_name' => 'PHINMA COC – Carmen']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $this->carmen->id]);
        $this->labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->carmen->id]);
    }

    private function reportFor(User $borrower, ?User $faculty): DamageReport
    {
        $location = Location::firstOrCreate(['campus_id' => $this->carmen->id, 'location_name' => 'Lab Storage Room – Carmen']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Test Tube (15 mL)', 'category' => 'Glassware', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $this->carmen->id, 'location_id' => $location->id, 'total_quantity' => 40, 'available_quantity' => 39]);
        $borrowing = Borrowing::create(['user_id' => $borrower->id, 'faculty_id' => $faculty?->id, 'borrow_date' => now(), 'return_date' => now(), 'status' => 'returned', 'claim_code' => 'CHEM-' . uniqid()]);
        BorrowingEquipment::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'inventory_id' => $inventory->id, 'quantity' => 40, 'condition_on_borrow' => 'good']);

        return DamageReport::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'equipment_inventory_id' => $inventory->id, 'quantity' => 1, 'reported_by' => $this->labStaff->id, 'description' => '1 out of 40 unit(s) returned damaged by lab staff.', 'liable_party' => 'student', 'status' => 'pending']);
    }

    public function test_admin_and_lab_staff_see_the_same_affiliation_details(): void
    {
        // Leftover values in the other role's field must not be shown.
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $this->carmen->id, 'email' => 'nidu.clarito.coc@phinmaed.com', 'student_id' => 'S-1', 'course' => 'Civil Engineering', 'department' => 'Nursing']);
        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->carmen->id, 'email' => 'josa.saliling.coc@phinmaed.com', 'department' => 'Civil Engineering', 'course' => 'Psychology']);
        $report = $this->reportFor($student, $faculty);

        foreach (["/admin/damage-reports/{$report->id}" => $this->admin, "/lab/damage-reports/{$report->id}" => $this->labStaff] as $url => $user) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('affiliations.borrower.email', 'nidu.clarito.coc@phinmaed.com')
                    ->where('affiliations.borrower.campus', 'PHINMA COC – Carmen')
                    ->where('affiliations.borrower.department_label', 'Course')
                    ->where('affiliations.borrower.department', 'Civil Engineering')
                    ->where('affiliations.faculty.email', 'josa.saliling.coc@phinmaed.com')
                    ->where('affiliations.faculty.campus', 'PHINMA COC – Carmen')
                    ->where('affiliations.faculty.department', 'Civil Engineering')
                    // Existing details are unchanged.
                    ->where('report.description', '1 out of 40 unit(s) returned damaged by lab staff.')
                    ->where('report.liable_party', 'student')
                    ->has('history'));
        }
    }

    public function test_missing_information_is_left_empty_and_no_faculty_is_null(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => null, 'course' => null]);
        $report = $this->reportFor($student, null);

        $this->actingAs($this->admin)->get("/admin/damage-reports/{$report->id}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('affiliations.borrower.campus', null)
                ->where('affiliations.borrower.department', null)
                ->where('affiliations.faculty', null));
    }

    public function test_other_campuses_still_cannot_open_the_report(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $this->carmen->id, 'course' => 'Nursing']);
        $report = $this->reportFor($student, null);

        $puerto = Campus::create(['campus_name' => 'PHINMA COC – Puerto']);
        $this->actingAs(User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $puerto->id]))
            ->get("/lab/damage-reports/{$report->id}")->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $puerto->id]))
            ->get("/admin/damage-reports/{$report->id}")->assertForbidden();
    }
}
