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
 * A borrower's self-filed damage report previously had no quantity field at
 * all, no check that the named equipment belonged to the borrowing, and
 * (because there was no field to check) no cap against what was actually
 * borrowed. These tests cover the fix: quantity is required and positive,
 * cannot exceed the borrowed amount, must reference equipment genuinely
 * part of the borrowing, and repeated reports can't cumulatively claim more
 * than was ever borrowed.
 */
class DamageReportQuantityValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeBorrowedItem(int $quantity = 3): array
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Beaker', 'condition' => 'good', 'is_available' => true]);
        $otherEquipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Not Borrowed', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 10, 'available_quantity' => 10 - $quantity]);

        // campus_id/student_id/course are required for the 'profile.complete'
        // middleware on student routes — without them the request redirects
        // to the complete-profile page before ever reaching the controller.
        $student = User::factory()->create([
            'role' => 'student', 'status' => 'active',
            'campus_id' => $campus->id, 'student_id' => 'S-' . uniqid(), 'course' => 'BSIT',
        ]);
        $borrowing = Borrowing::create(['user_id' => $student->id, 'borrow_date' => now(), 'return_date' => now()->addDays(3), 'status' => 'borrowed']);
        BorrowingEquipment::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'inventory_id' => $inventory->id, 'quantity' => $quantity, 'condition_on_borrow' => 'good']);

        return compact('student', 'borrowing', 'equipment', 'otherEquipment');
    }

    public function test_reporting_more_damaged_than_borrowed_is_rejected(): void
    {
        ['student' => $student, 'borrowing' => $borrowing, 'equipment' => $equipment] = $this->makeBorrowedItem(quantity: 3);

        $response = $this->actingAs($student)->post("/student/borrowings/{$borrowing->id}/report-damage", [
            'equipment_id' => $equipment->id,
            'quantity' => 4,
            'description' => 'Dropped and cracked.',
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, DamageReport::count());
    }

    public function test_reporting_within_the_borrowed_quantity_succeeds(): void
    {
        ['student' => $student, 'borrowing' => $borrowing, 'equipment' => $equipment] = $this->makeBorrowedItem(quantity: 3);

        $response = $this->actingAs($student)->post("/student/borrowings/{$borrowing->id}/report-damage", [
            'equipment_id' => $equipment->id,
            'quantity' => 2,
            'description' => 'Two units cracked.',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, DamageReport::count());
        $this->assertSame(2, DamageReport::first()->quantity);
    }

    public function test_quantity_must_be_a_positive_integer(): void
    {
        ['student' => $student, 'borrowing' => $borrowing, 'equipment' => $equipment] = $this->makeBorrowedItem(quantity: 3);

        $zero = $this->actingAs($student)->post("/student/borrowings/{$borrowing->id}/report-damage", [
            'equipment_id' => $equipment->id, 'quantity' => 0, 'description' => 'x',
        ]);
        $negative = $this->actingAs($student)->post("/student/borrowings/{$borrowing->id}/report-damage", [
            'equipment_id' => $equipment->id, 'quantity' => -1, 'description' => 'x',
        ]);

        $zero->assertSessionHasErrors('quantity');
        $negative->assertSessionHasErrors('quantity');
    }

    public function test_equipment_not_part_of_the_borrowing_is_rejected(): void
    {
        ['student' => $student, 'borrowing' => $borrowing, 'otherEquipment' => $otherEquipment] = $this->makeBorrowedItem(quantity: 3);

        $response = $this->actingAs($student)->post("/student/borrowings/{$borrowing->id}/report-damage", [
            'equipment_id' => $otherEquipment->id,
            'quantity' => 1,
            'description' => 'Claiming damage on something never borrowed.',
        ]);

        $response->assertSessionHasErrors('equipment_id');
        $this->assertSame(0, DamageReport::count());
    }

    public function test_repeated_reports_cannot_cumulatively_exceed_the_borrowed_quantity(): void
    {
        ['student' => $student, 'borrowing' => $borrowing, 'equipment' => $equipment] = $this->makeBorrowedItem(quantity: 3);

        $first = $this->actingAs($student)->post("/student/borrowings/{$borrowing->id}/report-damage", [
            'equipment_id' => $equipment->id, 'quantity' => 2, 'description' => 'First report.',
        ]);
        $first->assertSessionDoesntHaveErrors();

        // Only 1 unit "remaining" to report now (3 borrowed - 2 already reported).
        $second = $this->actingAs($student)->post("/student/borrowings/{$borrowing->id}/report-damage", [
            'equipment_id' => $equipment->id, 'quantity' => 2, 'description' => 'Second report tries to double-claim.',
        ]);

        $second->assertSessionHasErrors('quantity');
        $this->assertSame(2, DamageReport::sum('quantity'), 'Total reported damage must never exceed what was borrowed.');
    }
}
