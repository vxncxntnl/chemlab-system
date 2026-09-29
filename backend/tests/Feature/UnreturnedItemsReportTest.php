<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The new Admin > Reports > Unreturned Items page. Covers the requested
 * columns (borrower, id, item, quantity, dates, status), that it only shows
 * items genuinely still out (not pending/returned/cancelled), and that an
 * item is flagged overdue based on the real date even if the daily
 * scheduled command hasn't run to flip the stored status yet.
 */
class UnreturnedItemsReportTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campus = Campus::create(['campus_name' => 'Main Campus']);
        $this->location = Location::create(['campus_id' => $this->campus->id, 'location_name' => 'Lab']);
    }

    private function makeBorrowing(string $status, string $returnDate, array $userAttrs = []): Borrowing
    {
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Microscope', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $this->campus->id, 'location_id' => $this->location->id, 'total_quantity' => 5, 'available_quantity' => 4]);
        $student = User::factory()->create(array_merge(['role' => 'student', 'status' => 'active', 'student_id' => 'S-' . uniqid()], $userAttrs));

        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'borrow_date' => now()->subDays(5),
            'return_date' => $returnDate,
            'status' => $status,
        ]);
        BorrowingEquipment::create([
            'borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id,
            'inventory_id' => $inventory->id, 'quantity' => 2, 'condition_on_borrow' => 'good',
        ]);

        return $borrowing;
    }

    public function test_admin_can_view_the_unreturned_items_report(): void
    {
        $this->makeBorrowing('borrowed', now()->addDays(2)->toDateString());
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->get('/admin/reports/unreturned-items');

        $response->assertOk();
    }

    public function test_only_borrowed_and_overdue_items_are_included(): void
    {
        $this->makeBorrowing('borrowed', now()->addDays(2)->toDateString());
        $this->makeBorrowing('overdue', now()->subDays(1)->toDateString());
        $this->makeBorrowing('returned', now()->subDays(1)->toDateString());
        $this->makeBorrowing('pending_admin', now()->addDays(2)->toDateString());
        $this->makeBorrowing('cancelled', now()->addDays(2)->toDateString());
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->get('/admin/reports/unreturned-items');

        $items = $response->getOriginalContent()->getData()['page']['props']['items'];
        $this->assertCount(2, $items, 'Only borrowed/overdue items should appear — not pending, returned, or cancelled ones.');
    }

    public function test_an_item_past_its_return_date_is_flagged_overdue_even_if_status_column_is_stale(): void
    {
        // Status still says 'borrowed' (as it would if the daily
        // scheduled command hasn't run) even though the return date has
        // already passed — the report must catch this live, not just trust
        // the stored status.
        $this->makeBorrowing('borrowed', now()->subDays(3)->toDateString());
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->get('/admin/reports/unreturned-items');

        $items = $response->getOriginalContent()->getData()['page']['props']['items'];
        $this->assertTrue($items[0]['is_overdue'], 'Must be flagged overdue based on the real return date, not just the stored status.');
        $this->assertGreaterThanOrEqual(3, $items[0]['days_overdue']);
    }

    public function test_report_includes_all_requested_columns(): void
    {
        $this->makeBorrowing('borrowed', now()->addDays(2)->toDateString(), ['name' => 'Juan dela Cruz', 'student_id' => 'S-12345']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->get('/admin/reports/unreturned-items');

        $item = $response->getOriginalContent()->getData()['page']['props']['items'][0];
        $this->assertSame('Juan dela Cruz', $item['borrower_name']);
        $this->assertSame('S-12345', $item['borrower_id']);
        $this->assertSame('Microscope', $item['equipment_name']);
        $this->assertSame(2, $item['quantity']);
        $this->assertNotNull($item['borrow_date']);
        $this->assertNotNull($item['return_date']);
        $this->assertSame('borrowed', $item['status']);
    }

    public function test_a_student_cannot_access_the_unreturned_items_report(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $response = $this->actingAs($student)->get('/admin/reports/unreturned-items');

        $response->assertForbidden();
    }
}
