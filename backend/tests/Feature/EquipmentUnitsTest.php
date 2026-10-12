<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\EquipmentUnit;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Serial numbers per unit for instruments/electronics: units are created
 * when tracking is switched on, serials are editable on the View page, and
 * maintenance names the exact unit, which then shows "Under Maintenance".
 */
class EquipmentUnitsTest extends TestCase
{
    use RefreshDatabase;

    private Campus $carmen;
    private User $admin;
    private User $labStaff;
    private Equipment $microscope;
    private Equipment $beaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->carmen = Campus::create(['campus_name' => 'PHINMA COC – Carmen']);
        $location = Location::create(['campus_id' => $this->carmen->id, 'location_name' => 'Lab Storage Room – Carmen']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $this->carmen->id]);
        $this->labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->carmen->id]);

        $this->microscope = Equipment::create(['equipment_code' => 'EQP-00005', 'name' => 'Compound Microscope', 'category' => 'Microscopy', 'serial_number' => 'KJ80103156544M', 'condition' => 'good', 'is_available' => true]);
        $this->beaker = Equipment::create(['equipment_code' => 'EQP-00001', 'name' => 'Beaker (250 mL)', 'category' => 'Glassware', 'condition' => 'good', 'is_available' => true]);
        foreach ([[$this->microscope, 3], [$this->beaker, 20]] as [$eq, $qty]) {
            EquipmentInventory::create(['equipment_id' => $eq->id, 'campus_id' => $this->carmen->id, 'location_id' => $location->id, 'total_quantity' => $qty, 'available_quantity' => $qty]);
        }

        // Switch serial tracking on for the microscope (as on the Edit page).
        $this->actingAs($this->admin)->put("/admin/equipment/{$this->microscope->id}", [
            'name' => 'Compound Microscope', 'category' => 'Microscopy', 'serial_number' => 'KJ80103156544M',
            'condition' => 'good', 'is_available' => true, 'tracks_units' => true,
        ])->assertSessionHasNoErrors();
    }

    private function unit(int $n): EquipmentUnit
    {
        return EquipmentUnit::where('unit_code', sprintf('EQP-00005-%02d', $n))->sole();
    }

    private function maintenance(User $as, array $data)
    {
        $prefix = $as->role === 'admin' ? 'admin' : 'lab';

        return $this->actingAs($as)->post("/{$prefix}/maintenance", $data + [
            'type' => 'repair', 'description' => 'Fine focus knob loose',
            'maintenance_date' => now()->toDateString(), 'status' => 'in_progress',
        ]);
    }

    public function test_switching_tracking_on_makes_one_unit_per_item_in_stock(): void
    {
        $this->assertSame(3, $this->microscope->units()->count());
        $this->assertSame('KJ80103156544M', $this->unit(1)->serial_number, 'The existing serial goes on the first unit.');
        $this->assertNull($this->unit(2)->serial_number);
        $this->assertSame(0, EquipmentUnit::where('equipment_id', $this->beaker->id)->count(), 'Glassware stays counted by quantity.');

        $this->actingAs($this->admin)->get('/admin/equipment')->assertInertia(fn ($page) => $page
            ->where('equipment.data', fn ($rows) => collect($rows)->firstWhere('id', $this->microscope->id)['units_count'] === 3));
    }

    public function test_serial_numbers_can_be_edited_added_and_removed(): void
    {
        $this->actingAs($this->admin)->put("/admin/equipment-units/{$this->unit(2)->id}", ['serial_number' => 'KJ80103156545M', 'notes' => 'Left cabinet'])
            ->assertSessionHas('success');
        $this->assertSame('KJ80103156545M', $this->unit(2)->serial_number);

        // The same serial can't be on two units.
        $this->actingAs($this->admin)->put("/admin/equipment-units/{$this->unit(3)->id}", ['serial_number' => 'KJ80103156545M'])
            ->assertSessionHasErrors('serial_number');

        $this->actingAs($this->admin)->post("/admin/equipment/{$this->microscope->id}/units", ['serial_number' => 'NEW-SN-1'])->assertSessionHas('success');
        $this->assertSame('NEW-SN-1', $this->unit(4)->serial_number);

        $this->actingAs($this->admin)->delete("/admin/equipment-units/{$this->unit(4)->id}")->assertSessionHas('success');
        $this->assertSame(3, $this->microscope->units()->count());
    }

    public function test_a_unit_under_maintenance_shows_on_the_view_page_until_the_log_is_finished(): void
    {
        // Serial-tracked: the exact unit is required.
        $this->maintenance($this->admin, ['equipment_id' => $this->microscope->id])->assertSessionHasErrors('equipment_unit_id');
        $this->maintenance($this->admin, ['equipment_id' => $this->microscope->id, 'equipment_unit_id' => $this->unit(2)->id])->assertSessionHasNoErrors();
        $log = MaintenanceLog::sole();
        $this->assertSame($this->unit(2)->id, $log->equipment_unit_id);

        $this->actingAs($this->admin)->get("/admin/equipment/{$this->microscope->id}")->assertInertia(fn ($page) => $page
            ->has('units', 3)
            ->where('units.1.unit_code', 'EQP-00005-02')
            ->where('units.1.maintenance.id', $log->id)
            ->where('units.0.maintenance', null));

        // Can't remove a unit while it's being maintained.
        $this->actingAs($this->admin)->delete("/admin/equipment-units/{$this->unit(2)->id}")->assertSessionHas('error');

        // Finished: available again.
        $this->actingAs($this->admin)->put("/admin/maintenance/{$log->id}", [
            'equipment_id' => $this->microscope->id, 'equipment_unit_id' => $this->unit(2)->id,
            'type' => 'repair', 'description' => 'Fixed', 'maintenance_date' => now()->toDateString(), 'status' => 'completed',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get("/admin/equipment/{$this->microscope->id}")
            ->assertInertia(fn ($page) => $page->where('units.1.maintenance', null));
    }

    public function test_lab_staff_also_pick_the_unit_and_glassware_needs_no_unit(): void
    {
        $this->actingAs($this->labStaff)->get('/lab/maintenance/create')->assertInertia(fn ($page) => $page
            ->where('equipment', fn ($items) => count(collect($items)->firstWhere('id', $this->microscope->id)['units']) === 3
                && count(collect($items)->firstWhere('id', $this->beaker->id)['units']) === 0));

        $this->maintenance($this->labStaff, ['equipment_id' => $this->microscope->id])->assertSessionHasErrors('equipment_unit_id');
        $this->maintenance($this->labStaff, ['equipment_id' => $this->microscope->id, 'equipment_unit_id' => $this->unit(1)->id])->assertSessionHasNoErrors();
        $this->maintenance($this->labStaff, ['equipment_id' => $this->beaker->id])->assertSessionHasNoErrors();

        $this->assertSame(2, MaintenanceLog::count());
        $this->assertNull(MaintenanceLog::where('equipment_id', $this->beaker->id)->value('equipment_unit_id'));
    }

    public function test_another_campus_cannot_touch_these_units(): void
    {
        $puerto = Campus::create(['campus_name' => 'PHINMA COC – Puerto']);
        $puertoAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $puerto->id]);
        $puertoStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $puerto->id]);

        $this->actingAs($puertoAdmin)->put("/admin/equipment-units/{$this->unit(1)->id}", ['serial_number' => 'HACKED'])->assertForbidden();
        $this->assertSame('KJ80103156544M', $this->unit(1)->serial_number);

        // A unit of another campus can't be named in a maintenance log.
        $this->maintenance($puertoStaff, ['equipment_id' => $this->microscope->id, 'equipment_unit_id' => $this->unit(1)->id])->assertSessionHasErrors();
        $this->assertSame(0, MaintenanceLog::count());
    }
}
