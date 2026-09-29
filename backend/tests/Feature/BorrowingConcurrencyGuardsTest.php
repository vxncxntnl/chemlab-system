<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InvalidBorrowingStateException;
use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use App\Services\BorrowingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These cover the GUARD LOGIC that closes the overselling / double-return
 * race conditions (locked re-check, correct rejection). They run against a
 * single in-memory database and can't reproduce true multi-process
 * concurrency — that was verified separately with two real PHP processes
 * against the actual sqlite file (see project notes). What these protect
 * against is a future change silently removing the guard itself.
 */
class BorrowingConcurrencyGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function makeInventory(int $available): EquipmentInventory
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create([
            'equipment_code' => 'EQ-' . uniqid(),
            'name' => 'Test Item',
            'condition' => 'good',
            'is_available' => true,
        ]);

        return EquipmentInventory::create([
            'equipment_id' => $equipment->id,
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'total_quantity' => $available,
            'available_quantity' => $available,
        ]);
    }

    public function test_create_borrowing_rejects_when_quantity_exceeds_available_stock(): void
    {
        $inventory = $this->makeInventory(available: 1);
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->expectException(InsufficientInventoryException::class);

        app(BorrowingService::class)->createBorrowing([
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ], [
            ['equipment_id' => $inventory->equipment_id, 'inventory_id' => $inventory->id, 'quantity' => 2],
        ], $user->id);
    }

    public function test_create_borrowing_succeeds_and_decrements_exactly_once_when_stock_is_sufficient(): void
    {
        $inventory = $this->makeInventory(available: 3);
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        app(BorrowingService::class)->createBorrowing([
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ], [
            ['equipment_id' => $inventory->equipment_id, 'inventory_id' => $inventory->id, 'quantity' => 2],
        ], $user->id);

        $this->assertSame(1, $inventory->fresh()->available_quantity);
    }

    public function test_returning_an_already_returned_borrowing_is_rejected(): void
    {
        $inventory = $this->makeInventory(available: 0);
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $borrowing = Borrowing::create([
            'user_id' => $user->id,
            'borrow_date' => now(),
            'return_date' => now()->addDays(3),
            'status' => 'returned', // already returned
        ]);
        $item = BorrowingEquipment::create([
            'borrowing_id' => $borrowing->id,
            'equipment_id' => $inventory->equipment_id,
            'inventory_id' => $inventory->id,
            'quantity' => 1,
            'condition_on_borrow' => 'good',
        ]);

        $this->expectException(InvalidBorrowingStateException::class);

        app(BorrowingService::class)->returnBorrowing($borrowing, [$item->id => ['good' => 1, 'damaged' => 0]], $user->id);
    }

    public function test_returning_a_currently_borrowed_item_succeeds_and_restocks_exactly_once(): void
    {
        $inventory = $this->makeInventory(available: 0);
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $borrowing = Borrowing::create([
            'user_id' => $user->id,
            'borrow_date' => now(),
            'return_date' => now()->addDays(3),
            'status' => 'borrowed',
        ]);
        $item = BorrowingEquipment::create([
            'borrowing_id' => $borrowing->id,
            'equipment_id' => $inventory->equipment_id,
            'inventory_id' => $inventory->id,
            'quantity' => 1,
            'condition_on_borrow' => 'good',
        ]);

        app(BorrowingService::class)->returnBorrowing($borrowing, [$item->id => ['good' => 1, 'damaged' => 0]], $user->id);

        $this->assertSame(1, $inventory->fresh()->available_quantity);
        $this->assertSame('returned', $borrowing->fresh()->status);
    }
}
