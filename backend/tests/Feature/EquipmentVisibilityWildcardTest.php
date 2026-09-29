<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentVisibilityWildcardTest extends TestCase
{
    use RefreshDatabase;

    private function makeRestrictedInventory(string $restriction): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create([
            'equipment_code' => 'EQ-' . uniqid(),
            'name' => 'Restricted Item',
            'condition' => 'good',
            'is_available' => true,
            'course_restriction' => $restriction,
        ]);
        EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id,
            'total_quantity' => 5, 'available_quantity' => 5,
        ]);
    }

    public function test_setting_course_to_percent_sign_no_longer_reveals_every_restricted_item(): void
    {
        $this->makeRestrictedInventory('BSN,BSMedTech');
        $student = User::factory()->create([
            'role' => 'student', 'status' => 'active', 'campus_id' => Campus::first()->id,
            'student_id' => 'S-1', 'course' => '%',
        ]);

        $response = $this->actingAs($student)->get('/student/equipment');

        $response->assertInertia(fn ($page) => $page->has('inventory.data', 0));
    }

    public function test_a_students_actual_matching_course_still_reveals_the_item(): void
    {
        $this->makeRestrictedInventory('BSIT,BSCS');
        $student = User::factory()->create([
            'role' => 'student', 'status' => 'active', 'campus_id' => Campus::first()->id,
            'student_id' => 'S-2', 'course' => 'BSIT',
        ]);

        $response = $this->actingAs($student)->get('/student/equipment');

        $response->assertInertia(fn ($page) => $page->has('inventory.data', 1));
    }

    public function test_a_non_matching_course_does_not_reveal_the_restricted_item(): void
    {
        $this->makeRestrictedInventory('BSN');
        $student = User::factory()->create([
            'role' => 'student', 'status' => 'active', 'campus_id' => Campus::first()->id,
            'student_id' => 'S-3', 'course' => 'BSIT',
        ]);

        $response = $this->actingAs($student)->get('/student/equipment');

        $response->assertInertia(fn ($page) => $page->has('inventory.data', 0));
    }

    public function test_unrestricted_equipment_is_always_visible(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create([
            'equipment_code' => 'EQ-' . uniqid(), 'name' => 'Open Item', 'condition' => 'good', 'is_available' => true,
        ]);
        EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id,
            'total_quantity' => 5, 'available_quantity' => 5,
        ]);
        $student = User::factory()->create([
            'role' => 'student', 'status' => 'active', 'campus_id' => $campus->id,
            'student_id' => 'S-4', 'course' => 'BSIT',
        ]);

        $response = $this->actingAs($student)->get('/student/equipment');

        $response->assertInertia(fn ($page) => $page->has('inventory.data', 1));
    }
}
