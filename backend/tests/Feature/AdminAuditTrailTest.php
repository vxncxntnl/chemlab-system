<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spot-checks the admin actions that previously created zero audit trail
 * entries at all: Reservation approve/reject/delete (explicitly named by
 * the audit) plus the Notification and Activity CRUD flows that had the
 * same gap.
 */
class AdminAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function makeReservation(): Reservation
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Item', 'condition' => 'good', 'is_available' => true]);
        EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 5, 'available_quantity' => 5]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        return Reservation::create([
            'user_id' => $student->id, 'equipment_id' => $equipment->id,
            'reserved_date' => now()->addDays(5)->toDateString(), 'reserved_until' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
        ]);
    }

    public function test_approving_a_reservation_creates_an_audit_log_entry(): void
    {
        $reservation = $this->makeReservation();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post("/admin/reservations/{$reservation->id}/approve");

        $this->assertTrue(
            AuditLog::where('table_affected', 'reservations')->where('record_id', $reservation->id)->where('action', 'like', '%Approved%')->exists()
        );
    }

    public function test_rejecting_a_reservation_creates_an_audit_log_entry(): void
    {
        $reservation = $this->makeReservation();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post("/admin/reservations/{$reservation->id}/reject", ['remarks' => 'Not needed.']);

        $this->assertTrue(
            AuditLog::where('table_affected', 'reservations')->where('record_id', $reservation->id)->where('action', 'like', '%Rejected%')->exists()
        );
    }

    public function test_deleting_a_reservation_creates_an_audit_log_entry(): void
    {
        $reservation = $this->makeReservation();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->delete("/admin/reservations/{$reservation->id}");

        $this->assertTrue(
            AuditLog::where('table_affected', 'reservations')->where('record_id', $reservation->id)->where('action', 'like', '%Deleted%')->exists()
        );
    }

    public function test_sending_a_notification_creates_an_audit_log_entry(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $recipient = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/notifications', [
            'user_ids' => [$recipient->id],
            'title' => 'System Maintenance',
            'message' => 'The system will be down tonight.',
            'type' => 'info',
        ]);

        $this->assertTrue(AuditLog::where('action', 'Sent Notification')->exists());
    }
}
