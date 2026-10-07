<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentReportQuantityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_report_shows_each_items_total_and_available_quantity_across_campuses(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $beaker250 = Equipment::create(['equipment_code' => 'EQP-1', 'name' => 'Beaker (250 mL)', 'condition' => 'good', 'is_available' => true]);
        $beaker500 = Equipment::create(['equipment_code' => 'EQP-2', 'name' => 'Beaker (500 mL)', 'condition' => 'good', 'is_available' => true]);
        foreach (['Carmen' => [25, 7], 'Iligan' => [10, 10]] as $name => [$total, $available]) {
            $campus = Campus::create(['campus_name' => $name]);
            $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
            EquipmentInventory::create(['equipment_id' => $beaker250->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => $total, 'available_quantity' => $available]);
        }

        $this->withoutVite()->actingAs($admin)->get('/admin/equipment-report')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('equipment.0.name', 'Beaker (250 mL)')
                ->where('equipment.0.total_quantity', fn ($v) => (int) $v === 35)
                ->where('equipment.0.available_quantity', fn ($v) => (int) $v === 17)
                ->where('equipment.1.name', 'Beaker (500 mL)')
                ->where('equipment.1.total_quantity', null));
    }
}
