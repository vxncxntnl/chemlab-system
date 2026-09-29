<?php

namespace Tests\Feature;

use App\Exceptions\DuplicateSubmissionException;
use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use App\Services\BorrowingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the double-click fix: submitting the exact same borrowing request
 * twice in quick succession (same user, same equipment/quantities, same
 * dates) must create exactly one Borrowing record, not two. The guard is an
 * atomic Cache::lock() keyed on the request's own content — these tests
 * confirm it actually blocks an identical resubmission, does NOT block a
 * legitimately different request from the same user, and that a failed
 * first attempt doesn't leave the user stuck unable to retry.
 */
class DuplicateSubmissionGuardTest extends TestCase
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

    public function test_submitting_the_identical_request_twice_creates_only_one_borrowing(): void
    {
        $inventory = $this->makeInventory(available: 5);
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $payload = [
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ];
        $equipment = [
            ['equipment_id' => $inventory->equipment_id, 'inventory_id' => $inventory->id, 'quantity' => 1],
        ];

        $service = app(BorrowingService::class);
        $service->createBorrowing($payload, $equipment, $user->id);

        $this->expectException(DuplicateSubmissionException::class);
        try {
            $service->createBorrowing($payload, $equipment, $user->id);
        } finally {
            $this->assertSame(1, Borrowing::count(), 'A double-click must not create a second Borrowing record.');
            $this->assertSame(4, $inventory->fresh()->available_quantity, 'Inventory must only be decremented once.');
        }
    }

    public function test_a_different_request_from_the_same_user_is_not_blocked(): void
    {
        $inventoryA = $this->makeInventory(available: 5);
        $inventoryB = $this->makeInventory(available: 5);
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $service = app(BorrowingService::class);
        $service->createBorrowing([
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ], [
            ['equipment_id' => $inventoryA->equipment_id, 'inventory_id' => $inventoryA->id, 'quantity' => 1],
        ], $user->id);

        // Different equipment entirely — must succeed, not be treated as a duplicate.
        $service->createBorrowing([
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ], [
            ['equipment_id' => $inventoryB->equipment_id, 'inventory_id' => $inventoryB->id, 'quantity' => 1],
        ], $user->id);

        $this->assertSame(2, Borrowing::count());
    }

    public function test_two_different_users_submitting_the_same_equipment_are_not_blocked_by_each_others_lock(): void
    {
        $inventory = $this->makeInventory(available: 5);
        $userA = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $userB = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $payload = [
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ];
        $equipment = [
            ['equipment_id' => $inventory->equipment_id, 'inventory_id' => $inventory->id, 'quantity' => 1],
        ];

        $service = app(BorrowingService::class);
        $service->createBorrowing($payload, $equipment, $userA->id);
        $service->createBorrowing($payload, $equipment, $userB->id);

        $this->assertSame(2, Borrowing::count(), 'The idempotency lock is per-user; two different users must both succeed.');
    }

    public function test_a_failed_submission_releases_its_lock_so_the_user_can_retry_immediately(): void
    {
        $inventory = $this->makeInventory(available: 1);
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $payload = [
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ];
        // Requests more than is available — this attempt fails.
        $equipment = [
            ['equipment_id' => $inventory->equipment_id, 'inventory_id' => $inventory->id, 'quantity' => 5],
        ];

        $service = app(BorrowingService::class);
        try {
            $service->createBorrowing($payload, $equipment, $user->id);
            $this->fail('Expected InsufficientInventoryException was not thrown.');
        } catch (\App\Exceptions\InsufficientInventoryException) {
            // expected
        }

        // Immediately retrying the exact same (still-failing) request must
        // surface the real "not enough stock" error again, not a stale
        // "duplicate submission" lock left over from the failed attempt.
        $this->expectException(\App\Exceptions\InsufficientInventoryException::class);
        $service->createBorrowing($payload, $equipment, $user->id);
    }
}
