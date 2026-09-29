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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The exploit these fix: deleting a User or Equipment record used to
 * hard-delete (via FK cascade) every Borrowing, BorrowingEquipment,
 * DamageReport, and AuditLog row that ever referenced them — destroying
 * the exact accountability trail a system like this exists to keep,
 * often at precisely the moment (a dispute, a problem user) it matters most.
 */
class SoftDeleteHistoryPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_user_preserves_their_borrowing_and_audit_history(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Item', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 5, 'available_quantity' => 5]);

        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $borrowing = Borrowing::create(['user_id' => $student->id, 'borrow_date' => now(), 'return_date' => now()->addDays(3), 'status' => 'returned']);
        BorrowingEquipment::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'inventory_id' => $inventory->id, 'quantity' => 1, 'condition_on_borrow' => 'good']);
        AuditLog::log($student->id, 'Created Borrow Request', 'borrowings', $borrowing->id);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->delete("/admin/users/{$student->id}");

        // The student's own row is gone from normal queries...
        $this->assertNull(User::find($student->id));
        // ...but the borrowing record, and its link back to who made it,
        // both still resolve correctly — this is the actual fix.
        $survivingBorrowing = Borrowing::find($borrowing->id);
        $this->assertNotNull($survivingBorrowing, 'The borrowing record itself must survive deleting the user who made it.');
        $this->assertNotNull($survivingBorrowing->user, 'Borrowing::user() must still resolve via withTrashed() after the user is soft-deleted.');
        $this->assertSame($student->id, $survivingBorrowing->user->id);

        $survivingLog = AuditLog::where('record_id', $borrowing->id)->where('table_affected', 'borrowings')->first();
        $this->assertNotNull($survivingLog);
        $this->assertNotNull($survivingLog->user, 'AuditLog::user() must still resolve after the actor is soft-deleted — this is the core accountability guarantee.');
    }

    public function test_deleting_equipment_preserves_its_borrowing_and_damage_history(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Fragile Item', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 5, 'available_quantity' => 5]);

        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $borrowing = Borrowing::create(['user_id' => $student->id, 'borrow_date' => now(), 'return_date' => now()->addDays(3), 'status' => 'returned']);
        $item = BorrowingEquipment::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'inventory_id' => $inventory->id, 'quantity' => 1, 'condition_on_borrow' => 'good']);
        $report = DamageReport::create([
            'borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'quantity' => 1,
            'equipment_inventory_id' => $inventory->id, 'reported_by' => $student->id,
            'description' => 'cracked lens', 'status' => 'resolved',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->delete("/admin/equipment/{$equipment->id}");

        $this->assertNull(Equipment::find($equipment->id), 'Equipment must disappear from normal listings once deleted.');

        $survivingItem = BorrowingEquipment::find($item->id);
        $this->assertNotNull($survivingItem->equipment, 'BorrowingEquipment::equipment() must still resolve after the equipment is soft-deleted.');
        $this->assertSame($equipment->id, $survivingItem->equipment->id);

        $survivingReport = DamageReport::find($report->id);
        $this->assertNotNull($survivingReport->equipment, 'DamageReport::equipment() must still resolve after the equipment is soft-deleted.');
    }
}
