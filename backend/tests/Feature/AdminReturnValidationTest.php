<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin's "Mark Returned" action previously accepted the "conditions" input
 * completely unvalidated — any shape, any type. Its own frontend button
 * posts no body at all (an implicit "everything came back fine", which
 * returnBorrowing() already defaults correctly), so conditions has to stay
 * optional rather than required like Lab's more detailed form — but when
 * data IS present, it's now actually type-checked.
 */
class AdminReturnValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeBorrowing(string $status): array
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Item', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 5, 'available_quantity' => 4]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $borrowing = Borrowing::create(['user_id' => $student->id, 'borrow_date' => now(), 'return_date' => now()->addDays(3), 'status' => $status]);
        $item = BorrowingEquipment::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'inventory_id' => $inventory->id, 'quantity' => 1, 'condition_on_borrow' => 'good']);

        return compact('borrowing', 'inventory', 'item');
    }

    public function test_valid_return_with_no_body_still_works_and_restores_inventory(): void
    {
        ['borrowing' => $borrowing, 'inventory' => $inventory] = $this->makeBorrowing('borrowed');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/return");

        $response->assertRedirect();
        $this->assertSame('returned', $borrowing->fresh()->status);
        $this->assertSame(5, $inventory->fresh()->available_quantity);
    }

    public function test_admin_can_record_a_damaged_item_like_lab_staff(): void
    {
        ['borrowing' => $borrowing, 'inventory' => $inventory, 'item' => $item] = $this->makeBorrowing('borrowed');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/return", [
            'conditions'    => [$item->id => ['good' => 0, 'damaged' => 1]],
            'remarks'       => 'Cracked at the base',
            'liable_party'  => 'student',
            'liable_reason' => 'Returned cracked; was good at release.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('returned', $borrowing->fresh()->status);
        $this->assertSame('Cracked at the base', $borrowing->fresh()->remarks);
        $this->assertSame(4, $inventory->fresh()->available_quantity, 'A damaged item is not put back into available stock.');
        $this->assertDatabaseHas('damage_reports', ['borrowing_id' => $borrowing->id]);
    }

    public function test_admin_can_record_a_good_return_with_remarks(): void
    {
        ['borrowing' => $borrowing, 'inventory' => $inventory, 'item' => $item] = $this->makeBorrowing('overdue');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/return", [
            'conditions' => [$item->id => ['good' => 1, 'damaged' => 0]],
            'remarks'    => 'Returned clean',
        ])->assertSessionHasNoErrors();

        $this->assertSame('returned', $borrowing->fresh()->status);
        $this->assertSame(5, $inventory->fresh()->available_quantity);
        $this->assertDatabaseMissing('damage_reports', ['borrowing_id' => $borrowing->id]);
    }

    public function test_an_already_returned_borrowing_cannot_be_returned_again(): void
    {
        ['borrowing' => $borrowing, 'inventory' => $inventory] = $this->makeBorrowing('returned');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/return");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(4, $inventory->fresh()->available_quantity, 'Inventory must not be double-restored.');
    }

    public function test_a_cancelled_borrowing_cannot_be_returned(): void
    {
        ['borrowing' => $borrowing] = $this->makeBorrowing('cancelled');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/return");

        $response->assertSessionHas('error');
        $this->assertSame('cancelled', $borrowing->fresh()->status);
    }

    public function test_an_invalid_borrowing_id_returns_a_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post('/admin/borrowings/999999/return');

        $response->assertNotFound();
    }

    public function test_malformed_condition_values_are_rejected_by_validation(): void
    {
        ['borrowing' => $borrowing, 'item' => $item] = $this->makeBorrowing('borrowed');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/return", [
            'conditions' => [$item->id => ['good' => 'not-a-number', 'damaged' => -5]],
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame('borrowed', $borrowing->fresh()->status, 'Invalid input must not be silently coerced into a state change.');
    }

    public function test_a_double_click_repeat_submission_only_returns_it_once(): void
    {
        ['borrowing' => $borrowing, 'inventory' => $inventory] = $this->makeBorrowing('borrowed');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/return");
        $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/return");

        $this->assertSame(5, $inventory->fresh()->available_quantity, 'A repeat submission must not restore inventory a second time.');
    }
}
