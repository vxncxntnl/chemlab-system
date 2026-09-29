<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faculty/admin rejection previously had no way to record why a request was
 * rejected — the student just got a generic "rejected" message with no
 * context. These cover the new optional reason: it's stored on the
 * borrowing, and surfaces in the student's in-app notification.
 */
class RejectionReasonTest extends TestCase
{
    use RefreshDatabase;

    private function makeBorrowing(string $status, array $extra = []): array
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Item', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 5, 'available_quantity' => 4]);
        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $borrowing = Borrowing::create(array_merge([
            'user_id' => $student->id,
            'faculty_id' => $faculty->id,
            'borrow_date' => now(),
            'return_date' => now()->addDays(3),
            'status' => $status,
        ], $extra));

        BorrowingEquipment::create([
            'borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id,
            'inventory_id' => $inventory->id, 'quantity' => 1, 'condition_on_borrow' => 'good',
        ]);

        return ['borrowing' => $borrowing, 'faculty' => $faculty, 'student' => $student, 'inventory' => $inventory];
    }

    public function test_faculty_reject_with_a_reason_stores_it_and_includes_it_in_the_notification(): void
    {
        ['borrowing' => $borrowing, 'faculty' => $faculty] = $this->makeBorrowing('pending_faculty');

        $response = $this->actingAs($faculty)->post("/faculty/borrowings/{$borrowing->id}/reject", [
            'reason' => 'Missing required safety training for this equipment.',
        ]);

        $response->assertRedirect();
        $this->assertSame('Missing required safety training for this equipment.', $borrowing->fresh()->rejection_reason);

        $notif = UserNotification::where('user_id', $borrowing->user_id)->where('title', 'like', '%Rejected%')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Missing required safety training', $notif->message);
    }

    public function test_admin_reject_with_a_reason_stores_it_and_includes_it_in_the_notification(): void
    {
        ['borrowing' => $borrowing] = $this->makeBorrowing('pending_admin');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/reject", [
            'reason' => 'Equipment needed for another scheduled activity.',
        ]);

        $response->assertRedirect();
        $this->assertSame('Equipment needed for another scheduled activity.', $borrowing->fresh()->rejection_reason);

        $notif = UserNotification::where('user_id', $borrowing->user_id)->where('title', 'like', '%Rejected%')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Equipment needed for another', $notif->message);
    }

    public function test_rejecting_without_a_reason_still_works_and_uses_the_generic_message(): void
    {
        ['borrowing' => $borrowing] = $this->makeBorrowing('pending_admin');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/reject");

        $response->assertRedirect();
        $this->assertNull($borrowing->fresh()->rejection_reason);

        $notif = UserNotification::where('user_id', $borrowing->user_id)->where('title', 'like', '%Rejected%')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('contact the laboratory administrator', $notif->message);
    }

    public function test_reason_is_capped_at_500_characters(): void
    {
        ['borrowing' => $borrowing] = $this->makeBorrowing('pending_admin');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/reject", [
            'reason' => str_repeat('x', 501),
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertNotSame('cancelled', $borrowing->fresh()->status, 'Invalid input must not partially apply the rejection.');
    }
}
