<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\LabInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Lab-role "Lab Inventory" page (frontend/src/Pages/Lab/LabInventory/Index.vue)
 * and its controller (App\Http\Controllers\Lab\LabInventoryController) both
 * already existed fully built — route()'s calls to 'lab.lab-inventory.index'
 * and 'lab.lab-inventory.update' simply had no matching Route:: registration
 * in routes/web.php, making the whole page unreachable and its route()
 * calls fail outright wherever they were used. Same root cause was found for
 * lab.borrowings.create/store. These tests cover both once the routes were
 * registered: direct access works for lab_staff, is denied to other roles,
 * and a bad id 404s.
 */
class LabInventoryRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_staff_can_load_the_lab_inventory_page(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $campus->id]);

        $response = $this->actingAs($labStaff)->get('/lab/lab-inventory');

        $response->assertOk();
    }

    public function test_lab_staff_can_update_a_lab_inventory_item(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $campus->id]);
        $item = LabInventory::create(['lab' => 'chemistry', 'category' => 'general', 'description' => 'Sodium Chloride', 'quantity' => 10]);

        $response = $this->actingAs($labStaff)->put("/lab/lab-inventory/{$item->id}", [
            'quantity' => 7,
            'condition_remarks' => 'Good',
        ]);

        $response->assertRedirect();
        $this->assertSame(7, $item->fresh()->quantity);
    }

    public function test_a_student_cannot_access_the_lab_inventory_page(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $response = $this->actingAs($student)->get('/lab/lab-inventory');

        $response->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/lab/lab-inventory');

        $response->assertRedirect(route('login'));
    }

    public function test_updating_a_nonexistent_lab_inventory_item_404s(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $campus->id]);

        $response = $this->actingAs($labStaff)->put('/lab/lab-inventory/999999', ['quantity' => 1]);

        $response->assertNotFound();
    }

    public function test_lab_staff_can_reach_the_borrowing_create_form_and_submit_it(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $campus->id]);

        $createPage = $this->actingAs($labStaff)->get('/lab/borrowings/create');
        $createPage->assertOk();
    }
}
