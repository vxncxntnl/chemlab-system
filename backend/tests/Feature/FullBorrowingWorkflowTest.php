<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The whole borrowing lifecycle through the real HTTP endpoints, as each
 * role would do it: request → (optional faculty) → admin approval →
 * lab staff release → return → completed, checking the borrowing limit,
 * role boundaries and stock at every step.
 */
class FullBorrowingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;
    private User $student;
    private User $faculty;
    private User $admin;
    private User $labStaff;
    private EquipmentInventory $beakers;
    private EquipmentInventory $flasks;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $this->campus->id, 'location_name' => 'Chem Lab']);
        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $this->campus->id, 'student_id' => '02-0000-00001', 'course' => 'Nursing']);
        $this->faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus->id, 'department' => 'Nursing']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->campus->id]);
        $this->beakers = $this->stock($location, 'Beaker', 10);
        $this->flasks = $this->stock($location, 'Flask', 5);
    }

    private function stock(Location $location, string $name, int $qty): EquipmentInventory
    {
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => $name, 'condition' => 'good', 'is_available' => true]);

        return EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $this->campus->id, 'location_id' => $location->id,
            'total_quantity' => $qty, 'available_quantity' => $qty,
        ]);
    }

    private function submit(array $items, string $purpose = 'Titration')
    {
        return $this->actingAs($this->student)->post('/student/borrowings', [
            'borrow_date' => now()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
            'purpose' => $purpose, 'subject' => 'Chemistry', 'faculty_id' => $this->faculty->id,
            'equipment' => $items,
        ]);
    }

    private function item(EquipmentInventory $inv, int $qty): array
    {
        return ['equipment_id' => $inv->equipment_id, 'inventory_id' => $inv->id, 'quantity' => $qty];
    }

    public function test_the_complete_borrowing_lifecycle(): void
    {
        // 1. One request with two different items = ONE transaction.
        $this->submit([$this->item($this->beakers, 3), $this->item($this->flasks, 2)])->assertSessionHasNoErrors();
        $request = Borrowing::latest('id')->first();
        $this->assertSame('pending_faculty', $request->status);
        $this->assertSame($this->faculty->id, $request->faculty_id, 'The real assigned faculty is linked.');
        $this->assertSame(7, $this->beakers->fresh()->available_quantity);
        $this->assertSame(3, $this->flasks->fresh()->available_quantity);

        // 2. Limit: a second request is fine, a third is refused.
        $this->submit([$this->item($this->beakers, 1)], 'Second')->assertSessionHasNoErrors();
        $this->submit([$this->item($this->beakers, 1)], 'Third')->assertSessionHasErrors('equipment');
        $this->assertSame(2, Borrowing::where('user_id', $this->student->id)->count());

        // 3. Lab staff can't approve; the route doesn't exist for them.
        $this->actingAs($this->labStaff)->post("/lab/borrowings/{$request->id}/approve")->assertNotFound();
        $this->actingAs($this->labStaff)->post("/admin/borrowings/{$request->id}/approve")->assertForbidden();

        // 4. Admin approves directly — faculty approval is optional.
        $this->actingAs($this->admin)->post("/admin/borrowings/{$request->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $request->fresh()->status);

        // 5. Lab staff release, with claim code + borrower ID check.
        $this->actingAs($this->labStaff)->post("/lab/borrowings/{$request->id}/release", ['claim_code' => 'WRONG', 'student_id' => '02-0000-00001', 'id_checked' => '1'])
            ->assertSessionHas('error');
        $this->actingAs($this->labStaff)->post("/lab/borrowings/{$request->id}/release", ['claim_code' => $request->fresh()->claim_code, 'student_id' => '02-0000-00001', 'id_checked' => '1'])
            ->assertSessionHas('success');
        $this->assertSame('borrowed', $request->fresh()->status);
        $this->assertSame(6, $this->beakers->fresh()->available_quantity, 'Release does not deduct stock a second time (3 here + 1 in the second request).');

        // 6. Return: all beakers good, one of two flasks damaged.
        $lines = $request->fresh()->borrowingEquipment->keyBy('inventory_id');
        $this->actingAs($this->labStaff)->post("/lab/borrowings/{$request->id}/return", [
            'conditions' => [
                $lines[$this->beakers->id]->id => ['good' => 3, 'damaged' => 0],
                $lines[$this->flasks->id]->id => ['good' => 1, 'damaged' => 1],
            ],
            'liable_party' => 'student', 'liable_reason' => 'Dropped during the activity.',
        ])->assertSessionHas('success');

        $this->assertSame('returned', $request->fresh()->status);
        $this->assertSame(9, $this->beakers->fresh()->available_quantity, 'Good items go back to stock (the second request still holds 1).');
        $this->assertSame(4, $this->flasks->fresh()->available_quantity, 'The damaged flask does not.');
        $this->assertDatabaseHas('damage_reports', ['borrowing_id' => $request->id]);

        // 7. Returning again can't add stock twice.
        $this->actingAs($this->labStaff)->post("/lab/borrowings/{$request->id}/return", [
            'conditions' => [$lines[$this->beakers->id]->id => ['good' => 3, 'damaged' => 0], $lines[$this->flasks->id]->id => ['good' => 2, 'damaged' => 0]],
        ])->assertSessionHas('error');
        $this->assertSame(9, $this->beakers->fresh()->available_quantity);
        $this->assertSame(4, $this->flasks->fresh()->available_quantity);

        // 8. The returned transaction no longer counts toward the limit.
        $this->submit([$this->item($this->beakers, 1)], 'After return')->assertSessionHasNoErrors();
        $this->assertSame(3, Borrowing::where('user_id', $this->student->id)->count());
    }

    public function test_overdue_requests_still_count_toward_the_limit(): void
    {
        $this->submit([$this->item($this->beakers, 1)])->assertSessionHasNoErrors();
        $this->submit([$this->item($this->beakers, 1)], 'Second')->assertSessionHasNoErrors();
        Borrowing::where('user_id', $this->student->id)->update(['status' => 'overdue']);

        $this->submit([$this->item($this->beakers, 1)], 'Third')->assertSessionHasErrors('equipment');
    }

    public function test_stock_can_never_go_negative(): void
    {
        $this->submit([$this->item($this->flasks, 6)])->assertSessionHasErrors('equipment');
        $this->assertSame(5, $this->flasks->fresh()->available_quantity);
        $this->assertSame(0, Borrowing::count());
    }

    public function test_the_limit_is_rechecked_inside_the_save_transaction(): void
    {
        // Simulate the race: two requests already exist by the time this
        // one reaches the transaction (the early check is bypassed).
        $this->submit([$this->item($this->beakers, 1)])->assertSessionHasNoErrors();
        $this->submit([$this->item($this->beakers, 1)], 'Second')->assertSessionHasNoErrors();

        $service = app(\App\Services\BorrowingService::class);
        $method = new \ReflectionMethod($service, 'doCreateBorrowing');

        $this->expectException(\App\Exceptions\BorrowingLimitExceededException::class);
        $method->invoke($service, [
            'borrow_date' => now()->toDateString(), 'return_date' => now()->addDay()->toDateString(), 'faculty_id' => $this->faculty->id,
        ], [$this->item($this->beakers, 1)], $this->student->id, false);
    }
}
