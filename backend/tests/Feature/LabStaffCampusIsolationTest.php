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
 * Lab staff must only be able to act on records whose equipment is stocked
 * at their own campus — never another campus's, even via a direct URL/API
 * request with a guessed or enumerated ID. These cover the state-changing
 * actions specifically (release, return, assess, resolve, log maintenance),
 * since the read-only list/detail views were already correctly scoped.
 */
class LabStaffCampusIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campusA;
    private Campus $campusB;
    private User $labStaffA;
    private User $labStaffB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campusA = Campus::create(['campus_name' => 'Campus A']);
        $this->campusB = Campus::create(['campus_name' => 'Campus B']);
        $this->labStaffA = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->campusA->id]);
        $this->labStaffB = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->campusB->id]);
    }

    private function makeBorrowingAtCampus(Campus $campus): Borrowing
    {
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Item', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id,
            'total_quantity' => 5, 'available_quantity' => 4,
        ]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'student_id' => 'S-' . uniqid()]);
        $borrowing = Borrowing::create([
            'user_id' => $student->id, 'borrow_date' => now(), 'return_date' => now()->addDays(3),
            'status' => 'approved', 'claim_code' => 'CHEM-REQ-TEST-' . uniqid(),
        ]);
        BorrowingEquipment::create([
            'borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id,
            'inventory_id' => $inventory->id, 'quantity' => 1, 'condition_on_borrow' => 'good',
        ]);
        return $borrowing;
    }

    public function test_lab_staff_cannot_release_a_borrowing_from_another_campus(): void
    {
        $borrowing = $this->makeBorrowingAtCampus($this->campusB);

        $response = $this->actingAs($this->labStaffA)->post("/lab/borrowings/{$borrowing->id}/release", [
            'claim_code' => $borrowing->claim_code,
            'student_id' => $borrowing->user->student_id ?? 'x',
        ]);

        $response->assertForbidden();
        $this->assertSame('approved', $borrowing->fresh()->status, 'Status must not change from a cross-campus request.');
    }

    public function test_lab_staff_cannot_return_a_borrowing_from_another_campus(): void
    {
        $borrowing = $this->makeBorrowingAtCampus($this->campusB);
        $borrowing->update(['status' => 'borrowed']);
        $item = $borrowing->borrowingEquipment()->first();

        $response = $this->actingAs($this->labStaffA)->post("/lab/borrowings/{$borrowing->id}/return", [
            'conditions' => [$item->id => ['good' => 1, 'damaged' => 0]],
        ]);

        $response->assertForbidden();
        $this->assertSame('borrowed', $borrowing->fresh()->status, 'Status must not change from a cross-campus request.');
    }

    public function test_lab_staff_can_release_and_return_a_borrowing_from_their_own_campus(): void
    {
        $borrowing = $this->makeBorrowingAtCampus($this->campusA);

        $release = $this->actingAs($this->labStaffA)->post("/lab/borrowings/{$borrowing->id}/release", [
            'claim_code' => $borrowing->claim_code,
            'student_id' => $borrowing->user->student_id ?? 'x',
        ]);
        $release->assertRedirect();
        $this->assertSame('borrowed', $borrowing->fresh()->status);

        $item = $borrowing->borrowingEquipment()->first();
        $return = $this->actingAs($this->labStaffA)->post("/lab/borrowings/{$borrowing->id}/return", [
            'conditions' => [$item->id => ['good' => 1, 'damaged' => 0]],
        ]);
        $return->assertRedirect();
        $this->assertSame('returned', $borrowing->fresh()->status);
    }

    public function test_lab_staff_cannot_assess_or_resolve_a_damage_report_from_another_campus(): void
    {
        $borrowing = $this->makeBorrowingAtCampus($this->campusB);
        $item = $borrowing->borrowingEquipment()->first();
        $report = DamageReport::create([
            'borrowing_id' => $borrowing->id, 'equipment_id' => $item->equipment_id,
            'quantity' => 1, 'equipment_inventory_id' => $item->inventory_id,
            'reported_by' => $this->labStaffB->id, 'description' => 'test', 'status' => 'pending',
        ]);

        $assess = $this->actingAs($this->labStaffA)->post("/lab/damage-reports/{$report->id}/assess");
        $assess->assertForbidden();
        $this->assertSame('pending', $report->fresh()->status);

        $resolve = $this->actingAs($this->labStaffA)->post("/lab/damage-reports/{$report->id}/resolve");
        $resolve->assertForbidden();
        $this->assertSame('pending', $report->fresh()->status);
    }

    public function test_lab_staff_cannot_log_maintenance_for_equipment_at_another_campus(): void
    {
        $locationB = Location::create(['campus_id' => $this->campusB->id, 'location_name' => 'Lab B']);
        $equipmentB = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Item B', 'condition' => 'good', 'is_available' => true]);
        EquipmentInventory::create([
            'equipment_id' => $equipmentB->id, 'campus_id' => $this->campusB->id, 'location_id' => $locationB->id,
            'total_quantity' => 2, 'available_quantity' => 2,
        ]);

        $response = $this->actingAs($this->labStaffA)->post('/lab/maintenance', [
            'equipment_id' => $equipmentB->id,
            'type' => 'routine',
            'description' => 'cross-campus attempt',
            'maintenance_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        $response->assertSessionHasErrors('equipment_id');
    }
}
