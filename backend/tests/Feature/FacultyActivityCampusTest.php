<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityEquipmentQr;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A faculty member's activity equipment list is limited to their own
 * campus — the same hard restriction Browse Equipment and New Request
 * already apply — on the backend, not just in the form.
 */
class FacultyActivityCampusTest extends TestCase
{
    use RefreshDatabase;

    private Campus $carmen;
    private Campus $iligan;
    private EquipmentInventory $carmenItem;
    private EquipmentInventory $iliganItem;
    private User $faculty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->carmen = Campus::create(['campus_name' => 'PHINMA COC – Carmen']);
        $this->iligan = Campus::create(['campus_name' => 'PHINMA COC – Iligan']);
        $this->carmenItem = $this->stockAt($this->carmen, 'Petri Dish');
        $this->iliganItem = $this->stockAt($this->iligan, 'Beaker');
        $this->faculty = User::factory()->create([
            'role' => 'faculty', 'status' => 'active', 'campus_id' => $this->carmen->id, 'department' => 'Nursing',
        ]);
    }

    private function stockAt(Campus $campus, string $name): EquipmentInventory
    {
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => $name, 'condition' => 'good', 'is_available' => true]);

        return EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id,
            'total_quantity' => 5, 'available_quantity' => 5,
        ]);
    }

    private function activityPayload(array $inventoryIds): array
    {
        return [
            'title' => 'Titration Lab', 'department' => 'Nursing', 'activity_date' => now()->addDay()->toDateString(),
            'status' => 'upcoming', 'equipment_inventory_ids' => $inventoryIds,
        ];
    }

    public function test_the_create_form_only_lists_the_faculty_members_own_campus(): void
    {
        $this->actingAs($this->faculty)->get('/faculty/activities/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('inventoryItems', 1)
                ->where('inventoryItems.0.id', $this->carmenItem->id));
    }

    public function test_equipment_from_another_campus_is_rejected_on_save(): void
    {
        $this->actingAs($this->faculty)->post('/faculty/activities', $this->activityPayload([$this->iliganItem->id]))
            ->assertSessionHasErrors('equipment_inventory_ids.0');

        $this->assertSame(0, Activity::count());
    }

    public function test_equipment_from_their_own_campus_is_accepted(): void
    {
        $this->actingAs($this->faculty)->post('/faculty/activities', $this->activityPayload([$this->carmenItem->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_equipment_qr', ['equipment_inventory_id' => $this->carmenItem->id]);
    }

    public function test_editing_keeps_equipment_the_activity_already_had(): void
    {
        $activity = Activity::create([
            'title' => 'Old Lab', 'instructor_id' => $this->faculty->id, 'department' => 'Nursing',
            'activity_date' => now()->addDay()->toDateString(), 'status' => 'upcoming',
        ]);
        ActivityEquipmentQr::findOrCreateFor($activity->id, $this->iliganItem->id); // attached before this rule existed

        $this->actingAs($this->faculty)->get("/faculty/activities/{$activity->id}/edit")
            ->assertInertia(fn ($page) => $page->has('inventoryItems', 2));

        $this->actingAs($this->faculty)
            ->put("/faculty/activities/{$activity->id}", $this->activityPayload([$this->iliganItem->id, $this->carmenItem->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $activity->equipmentQrCodes()->count());
    }

    public function test_a_faculty_account_without_a_campus_keeps_the_full_list(): void
    {
        $legacy = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => null]);

        $this->actingAs($legacy)->get('/faculty/activities/create')
            ->assertInertia(fn ($page) => $page->has('inventoryItems', 2));
    }
}
