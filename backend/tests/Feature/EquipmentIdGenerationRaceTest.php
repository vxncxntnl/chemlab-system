<?php

namespace Tests\Feature;

use App\Models\Equipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * equipment_code was generated from max(id)+1 with no lock between the read
 * and the write — two nearly-simultaneous creations could compute the same
 * next id and collide. The DB's own unique constraint on equipment_code
 * (already in place) is the real safety net; createWithUniqueCode() adds a
 * bounded retry that catches exactly that collision and regenerates. A true
 * concurrent-process race is verified separately (see project notes) since
 * PHPUnit's single-process, single-connection test run can't reproduce two
 * genuinely simultaneous database writers — this covers the retry logic
 * itself deterministically by forcing a collision.
 */
class EquipmentIdGenerationRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_creation_still_generates_a_sequential_code(): void
    {
        $equipment = Equipment::createWithUniqueCode([
            'name' => 'Microscope', 'condition' => 'good', 'is_available' => true,
        ]);

        $this->assertMatchesRegularExpression('/^EQP-\d{5}$/', $equipment->equipment_code);
    }

    public function test_retries_and_succeeds_when_the_first_generated_code_already_exists(): void
    {
        // Pre-create a row that will collide with the naive max(id)+1
        // calculation the next real insert would otherwise compute,
        // simulating the losing side of a real race.
        $existing = Equipment::createWithUniqueCode(['name' => 'Existing', 'condition' => 'good', 'is_available' => true]);
        $collidingCode = 'EQP-' . str_pad((string) ($existing->id + 1), 5, '0', STR_PAD_LEFT);
        Equipment::create(['equipment_code' => $collidingCode, 'name' => 'Manually Coded Item', 'condition' => 'good', 'is_available' => true]);

        // The next real creation would naively compute the same $collidingCode
        // (max(id)+1) — createWithUniqueCode() must catch the unique
        // constraint violation and retry rather than throwing.
        $new = Equipment::createWithUniqueCode(['name' => 'New Item', 'condition' => 'good', 'is_available' => true]);

        $this->assertNotSame($collidingCode, $new->equipment_code);
        $this->assertDatabaseCount('equipment', 3);
    }

    public function test_an_explicit_equipment_code_is_left_untouched(): void
    {
        $equipment = Equipment::createWithUniqueCode([
            'equipment_code' => 'CUSTOM-CODE-1', 'name' => 'Special Item', 'condition' => 'good', 'is_available' => true,
        ]);

        $this->assertSame('CUSTOM-CODE-1', $equipment->equipment_code);
    }
}
