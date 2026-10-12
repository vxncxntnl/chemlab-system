<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use App\Services\BorrowingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The claim code used to be derived from the borrowing's own sequential
 * public ID (CHEM-REQ-2026-00042) — trivially guessable by incrementing the
 * number. It's now a random token. These tests check the generated code no
 * longer encodes the row's id, has enough entropy to not be brute-forceable
 * in practice, is unique per borrowing, and that the release() lookup that
 * depends on it still works end-to-end.
 */
class ClaimCodeSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeInventory(): EquipmentInventory
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Item', 'condition' => 'good', 'is_available' => true]);

        return EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id,
            'total_quantity' => 10, 'available_quantity' => 10,
        ]);
    }

    public function test_claim_code_does_not_encode_the_borrowings_own_id(): void
    {
        $inventory = $this->makeInventory();
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $service = app(BorrowingService::class);

        $borrowing = $service->createBorrowing([
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ], [
            ['equipment_id' => $inventory->equipment_id, 'inventory_id' => $inventory->id, 'quantity' => 1],
        ], $user->id);

        $paddedId = str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT);
        $this->assertStringNotContainsString($paddedId, $borrowing->claim_code, 'Claim code must not be derived from the row id.');
        $this->assertMatchesRegularExpression('/^CHEM-\d{4}-[A-Z0-9]{10}$/', $borrowing->claim_code);
    }

    public function test_claim_codes_have_high_entropy_and_are_not_sequential_across_requests(): void
    {
        $inventory = $this->makeInventory();
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $service = app(BorrowingService::class);

        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            // Each iteration uses a distinct borrow_date so the (correctly
            // working) duplicate-submission guard from issue #1 doesn't
            // treat these as the same double-clicked request.
            $borrowing = $service->createBorrowing([
                'borrow_date' => now()->addDays(1 + $i)->toDateString(),
                'return_date' => now()->addDays(10 + $i)->toDateString(),
            ], [
                ['equipment_id' => $inventory->equipment_id, 'inventory_id' => $inventory->id, 'quantity' => 1],
            ], $user->id);
            $codes[] = $borrowing->claim_code;
        }

        $this->assertSame(5, count(array_unique($codes)), 'All generated codes must be unique.');

        // Knowing one code must not let you predict the next one — verify
        // the random suffixes aren't sequential/incrementing.
        $suffixes = array_map(fn ($c) => substr($c, -10), $codes);
        $this->assertNotEquals($suffixes[0], $suffixes[1]);
        sort($suffixes);
        $original = array_map(fn ($c) => substr($c, -10), $codes);
        $this->assertNotSame($original, $suffixes, 'Codes should not already be in sorted/incrementing order.');
    }

    public function test_release_still_works_end_to_end_with_the_new_claim_code_format(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $campus->id]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'student_id' => 'S-' . uniqid()]);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Item', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id,
            'total_quantity' => 5, 'available_quantity' => 5,
        ]);

        $service = app(BorrowingService::class);
        $borrowing = $service->createLabInitiatedBorrowing([
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ], [
            ['equipment_id' => $equipment->id, 'inventory_id' => $inventory->id, 'quantity' => 1],
        ], $labStaff->id);
        $borrowing->update(['status' => 'approved', 'user_id' => $student->id]);

        $response = $this->actingAs($labStaff)->post("/lab/borrowings/{$borrowing->id}/release", [
            'claim_code' => $borrowing->claim_code,
            'student_id' => $student->student_id,
            'id_checked' => '1',
        ]);

        $response->assertRedirect();
        $this->assertSame('borrowed', $borrowing->fresh()->status);
    }

    public function test_release_rejects_a_guessed_sequential_style_code(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $campus->id]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'student_id' => 'S-' . uniqid()]);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Item', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id,
            'total_quantity' => 5, 'available_quantity' => 5,
        ]);

        $service = app(BorrowingService::class);
        $borrowing = $service->createLabInitiatedBorrowing([
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ], [
            ['equipment_id' => $equipment->id, 'inventory_id' => $inventory->id, 'quantity' => 1],
        ], $labStaff->id);
        $borrowing->update(['status' => 'approved', 'user_id' => $student->id]);

        // The old, guessable format an attacker might still try.
        $guessed = 'CHEM-REQ-' . now()->format('Y') . '-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT);

        $response = $this->actingAs($labStaff)->post("/lab/borrowings/{$borrowing->id}/release", [
            'claim_code' => $guessed,
            'student_id' => $student->student_id,
            'id_checked' => '1',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('approved', $borrowing->fresh()->status);
    }
}
