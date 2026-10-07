<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The equipment report covers one campus: only equipment stocked there,
 * with that campus's quantities. Each size is its own row.
 */
class EquipmentReportQuantityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Campus $carmen;
    private Campus $iligan;
    private Equipment $beaker250;
    private Equipment $beaker500;
    private Equipment $iliganOnly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->carmen = Campus::create(['campus_name' => 'Carmen']);
        $this->iligan = Campus::create(['campus_name' => 'Iligan']);
        $this->beaker250 = Equipment::create(['equipment_code' => 'EQP-1', 'name' => 'Beaker (250 mL)', 'condition' => 'good', 'is_available' => true]);
        $this->beaker500 = Equipment::create(['equipment_code' => 'EQP-2', 'name' => 'Beaker (500 mL)', 'condition' => 'good', 'is_available' => true]);
        $this->iliganOnly = Equipment::create(['equipment_code' => 'EQP-3', 'name' => 'Microscope', 'condition' => 'good', 'is_available' => true]);
        $this->stock($this->carmen, $this->beaker250, 25, 7);
        $this->stock($this->carmen, $this->beaker500, 4, 4);
        $this->stock($this->iligan, $this->beaker250, 10, 10);
        $this->stock($this->iligan, $this->iliganOnly, 3, 3);
    }

    private function stock(Campus $campus, Equipment $equipment, int $total, int $available): void
    {
        $location = Location::firstOrCreate(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => $total, 'available_quantity' => $available]);
    }

    public function test_it_defaults_to_the_first_campus_and_shows_only_its_stock(): void
    {
        $this->actingAs($this->admin)->get('/admin/equipment-report')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('campus.campus_name', 'Carmen')
                ->has('equipment', 2)
                ->where('equipment.0.name', 'Beaker (250 mL)')
                ->where('equipment.0.total_quantity', fn ($v) => (int) $v === 25)
                ->where('equipment.0.available_quantity', fn ($v) => (int) $v === 7)
                ->where('equipment.1.name', 'Beaker (500 mL)')
                ->where('equipment.1.total_quantity', fn ($v) => (int) $v === 4));
    }

    public function test_it_defaults_to_the_admins_own_campus_when_set(): void
    {
        $this->admin->update(['campus_id' => $this->iligan->id]);

        $this->actingAs($this->admin)->get('/admin/equipment-report')
            ->assertInertia(fn ($page) => $page->where('campus.campus_name', 'Iligan')->has('equipment', 2));
    }

    public function test_another_campus_can_be_picked(): void
    {
        $this->actingAs($this->admin)->get('/admin/equipment-report?campus_id=' . $this->iligan->id)
            ->assertInertia(fn ($page) => $page
                ->where('campus.campus_name', 'Iligan')
                ->where('equipment.0.name', 'Beaker (250 mL)')
                ->where('equipment.0.total_quantity', fn ($v) => (int) $v === 10)
                ->where('equipment.1.name', 'Microscope'));
    }
}
