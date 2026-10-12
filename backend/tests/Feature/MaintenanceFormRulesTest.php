<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Maintenance form clean-up: no "Replacement" type (replacements go through
 * Damage Reports), a new log can't start as "Cancelled", a next date only for
 * recurring work, and a blank cost is saved as 0 instead of failing.
 */
class MaintenanceFormRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $labStaff;
    private Equipment $balance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $campus = Campus::create(['campus_name' => 'PHINMA COC – Carmen']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab Storage Room – Carmen']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $campus->id]);
        $this->labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $campus->id]);
        $this->balance = Equipment::create(['equipment_code' => 'EQP-1', 'name' => 'Hot Plate', 'condition' => 'good', 'is_available' => true]);
        EquipmentInventory::create(['equipment_id' => $this->balance->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 2, 'available_quantity' => 2]);
    }

    private function add(User $as, array $data)
    {
        $prefix = $as->role === 'admin' ? 'admin' : 'lab';

        return $this->actingAs($as)->post("/{$prefix}/maintenance", $data + [
            'equipment_id' => $this->balance->id, 'type' => 'routine', 'description' => 'Checked',
            'maintenance_date' => now()->toDateString(), 'status' => 'completed',
        ]);
    }

    public function test_new_logs_cannot_be_replacement_or_cancelled(): void
    {
        foreach ([$this->admin, $this->labStaff] as $user) {
            $this->add($user, ['type' => 'replacement'])->assertSessionHasErrors('type');
            $this->add($user, ['status' => 'cancelled'])->assertSessionHasErrors('status');
        }
        $this->assertSame(0, MaintenanceLog::count());
    }

    public function test_next_date_only_for_recurring_work_and_blank_cost_is_zero(): void
    {
        foreach ([$this->admin, $this->labStaff] as $user) {
            $this->add($user, ['type' => 'repair', 'next_maintenance_date' => now()->addMonth()->toDateString()])->assertSessionHasNoErrors();
            $this->add($user, ['type' => 'calibration', 'next_maintenance_date' => now()->addMonth()->toDateString()])->assertSessionHasNoErrors();
        }

        $this->assertSame(0, MaintenanceLog::where('type', 'repair')->whereNotNull('next_maintenance_date')->count(), 'A repair has no next date.');
        $this->assertSame(2, MaintenanceLog::where('type', 'calibration')->whereNotNull('next_maintenance_date')->count());
        $this->assertSame(0.0, (float) MaintenanceLog::where('performed_by', $this->admin->id)->value('cost'), 'Blank cost saves as 0.');
    }

    public function test_editing_can_still_cancel_and_older_replacement_logs_still_save(): void
    {
        $this->add($this->admin, ['status' => 'scheduled'])->assertSessionHasNoErrors();
        $log = MaintenanceLog::sole();
        $edit = fn (array $data) => $this->actingAs($this->admin)->put("/admin/maintenance/{$log->id}", $data + [
            'equipment_id' => $this->balance->id, 'type' => 'routine', 'description' => 'Checked', 'maintenance_date' => now()->toDateString(),
        ]);

        $edit(['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $log->fresh()->status);

        $log->update(['type' => 'replacement']); // a log saved before this change
        $edit(['type' => 'replacement', 'status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame('replacement', $log->fresh()->type);
    }
}
