<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The exact scenario from the audit:
 * 1. Equipment has 1 available unit.
 * 2. User creates reservation.
 * 3. Reservation is approved.
 * 4. Another user attempts to borrow the same equipment for overlapping dates.
 * 5. The system must prevent the conflicting transaction.
 *
 * (Reservation has no self-service creation endpoint anywhere in the app —
 * documented separately as its own finding — so step 2/3 are set up
 * directly here, exactly as they would exist if that feature is ever built,
 * or as they already can via Admin-side approve() on a seeded/imported row.)
 */
class ReservationBlocksConflictingBorrowTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_reservation_blocks_a_conflicting_borrow_by_someone_else(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Only Microscope', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 1, 'available_quantity' => 1]);

        $reservingUser = User::factory()->create(['role' => 'student', 'status' => 'active']);
        Reservation::create([
            'user_id' => $reservingUser->id,
            'equipment_id' => $equipment->id,
            'reserved_date' => now()->addDays(5)->toDateString(),
            'reserved_until' => now()->addDays(7)->toDateString(),
            'status' => 'approved',
            'approved_by' => $reservingUser->id,
        ]);

        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $campus->id, 'department' => 'Science']);
        $otherStudent = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $campus->id, 'student_id' => 'S-' . uniqid(), 'course' => 'BSIT']);

        $response = $this->actingAs($otherStudent)->post('/student/borrowings', [
            'borrow_date' => now()->addDays(6)->toDateString(), // overlaps the reservation
            'return_date' => now()->addDays(8)->toDateString(),
            'purpose' => 'conflicting attempt',
            'subject' => 'Test',
            'faculty_id' => $faculty->id,
            'equipment' => [[
                'equipment_id' => $equipment->id,
                'inventory_id' => $inventory->id,
                'quantity' => 1,
            ]],
        ]);

        $response->assertSessionHasErrors('equipment');
        $this->assertSame(1, $inventory->fresh()->available_quantity, 'Inventory must not be decremented for a rejected request.');
    }

    public function test_a_borrow_request_for_non_overlapping_dates_is_not_blocked(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Shared Item', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 1, 'available_quantity' => 1]);

        $reservingUser = User::factory()->create(['role' => 'student', 'status' => 'active']);
        Reservation::create([
            'user_id' => $reservingUser->id,
            'equipment_id' => $equipment->id,
            'reserved_date' => now()->addDays(20)->toDateString(),
            'reserved_until' => now()->addDays(22)->toDateString(),
            'status' => 'approved',
            'approved_by' => $reservingUser->id,
        ]);

        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $campus->id, 'department' => 'Science']);
        $otherStudent = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $campus->id, 'student_id' => 'S-' . uniqid(), 'course' => 'BSIT']);

        $response = $this->actingAs($otherStudent)->post('/student/borrowings', [
            'borrow_date' => now()->addDays(2)->toDateString(), // well before the reservation window
            'return_date' => now()->addDays(4)->toDateString(),
            'purpose' => 'non-conflicting',
            'subject' => 'Test',
            'faculty_id' => $faculty->id,
            'equipment' => [[
                'equipment_id' => $equipment->id,
                'inventory_id' => $inventory->id,
                'quantity' => 1,
            ]],
        ]);

        $response->assertSessionDoesntHaveErrors('equipment');
        $this->assertSame(0, $inventory->fresh()->available_quantity, 'A legitimate non-conflicting borrow should succeed and decrement stock normally.');
    }

    public function test_a_user_can_still_borrow_equipment_they_reserved_for_themselves(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Self Reserved Item', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 1, 'available_quantity' => 1]);

        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $campus->id, 'department' => 'Science']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $campus->id, 'student_id' => 'S-' . uniqid(), 'course' => 'BSIT']);

        Reservation::create([
            'user_id' => $student->id,
            'equipment_id' => $equipment->id,
            'reserved_date' => now()->addDays(5)->toDateString(),
            'reserved_until' => now()->addDays(7)->toDateString(),
            'status' => 'approved',
            'approved_by' => $student->id,
        ]);

        $response = $this->actingAs($student)->post('/student/borrowings', [
            'borrow_date' => now()->addDays(6)->toDateString(),
            'return_date' => now()->addDays(7)->toDateString(),
            'purpose' => 'using my own reservation',
            'subject' => 'Test',
            'faculty_id' => $faculty->id,
            'equipment' => [[
                'equipment_id' => $equipment->id,
                'inventory_id' => $inventory->id,
                'quantity' => 1,
            ]],
        ]);

        $response->assertSessionDoesntHaveErrors('equipment');
    }
}
