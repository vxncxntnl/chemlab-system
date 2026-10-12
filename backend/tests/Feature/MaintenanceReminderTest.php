<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\MaintenanceReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * In-app reminders for maintenance happening today (Philippine time), to the
 * campus's lab staff and admin — once per person per log per day — and the
 * activity QR time window read in Philippine time.
 */
class MaintenanceReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $labStaff;
    private User $otherCampusStaff;
    private Equipment $hotPlate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // 9:00 AM in the Philippines (01:00 UTC).
        $this->travelTo(Carbon::parse('2026-10-13 09:00', 'Asia/Manila')->utc());

        $carmen = Campus::create(['campus_name' => 'PHINMA COC – Carmen']);
        $puerto = Campus::create(['campus_name' => 'PHINMA COC – Puerto']);
        $location = Location::create(['campus_id' => $carmen->id, 'location_name' => 'Lab Storage Room – Carmen']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $carmen->id]);
        $this->labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $carmen->id]);
        $this->otherCampusStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $puerto->id]);
        $this->hotPlate = Equipment::create(['equipment_code' => 'EQP-1', 'name' => 'Hot Plate', 'condition' => 'good', 'is_available' => true]);
        EquipmentInventory::create(['equipment_id' => $this->hotPlate->id, 'campus_id' => $carmen->id, 'location_id' => $location->id, 'total_quantity' => 2, 'available_quantity' => 2]);
    }

    private function schedule(string $date, array $extra = [])
    {
        return $this->actingAs($this->admin)->post('/admin/maintenance', $extra + [
            'equipment_id' => $this->hotPlate->id, 'type' => 'repair', 'description' => 'Replace the heating coil',
            'maintenance_date' => $date, 'status' => 'scheduled',
        ]);
    }

    private function reminders(User $user, string $title = MaintenanceReminderService::TITLE_TODAY): int
    {
        return UserNotification::where('user_id', $user->id)->where('title', $title)->count();
    }

    public function test_scheduling_for_today_notifies_the_campus_lab_staff_right_away(): void
    {
        $this->schedule('2026-10-13')->assertSessionHasNoErrors();

        $this->assertSame(1, $this->reminders($this->labStaff));
        $this->assertStringContainsString('Hot Plate', UserNotification::where('user_id', $this->labStaff->id)->value('message'));
        $this->assertSame(0, $this->reminders($this->admin), 'The admin who scheduled it already knows.');
        $this->assertSame(0, $this->reminders($this->otherCampusStaff), 'Other campuses are not told.');
    }

    public function test_a_later_date_reminds_on_that_day_once(): void
    {
        $this->schedule('2026-10-15')->assertSessionHasNoErrors();
        $this->assertSame(0, $this->reminders($this->labStaff), 'Not yet.');

        $this->travelTo(Carbon::parse('2026-10-15 07:30', 'Asia/Manila')->utc());
        $this->actingAs($this->labStaff)->get('/lab/dashboard')->assertOk();
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();
        app(MaintenanceReminderService::class)->sendDueToday(); // a second run (e.g. the cron) doesn't repeat

        $this->assertSame(1, $this->reminders($this->labStaff));
        $this->assertSame(1, $this->reminders($this->admin));
    }

    public function test_today_means_philippine_today(): void
    {
        // 6:00 AM Oct 14 in the Philippines is still Oct 13 in UTC.
        $this->travelTo(Carbon::parse('2026-10-14 06:00', 'Asia/Manila')->utc());
        $this->schedule('2026-10-14')->assertSessionHasNoErrors();

        $this->assertSame(1, $this->reminders($this->labStaff));
    }

    public function test_recurring_work_reminds_on_its_next_maintenance_date(): void
    {
        $this->actingAs($this->admin)->post('/admin/maintenance', [
            'equipment_id' => $this->hotPlate->id, 'type' => 'calibration', 'description' => 'Calibrated',
            'maintenance_date' => '2026-10-13', 'next_maintenance_date' => '2026-11-13', 'status' => 'completed',
        ])->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-11-13 08:00', 'Asia/Manila')->utc());
        $this->actingAs($this->labStaff)->get('/lab/dashboard')->assertOk();

        $this->assertSame(1, $this->reminders($this->labStaff, MaintenanceReminderService::TITLE_DUE));
    }

    public function test_activity_qr_times_are_read_in_philippine_time(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->admin->campus_id, 'department' => 'Nursing']);
        $activity = Activity::create(['title' => 'Lab', 'instructor_id' => $faculty->id, 'department' => 'Nursing', 'activity_date' => '2026-10-13', 'start_time' => '09:00', 'end_time' => '12:00', 'status' => 'upcoming']);
        $activity->setQrWindowFromTimes();
        $activity->save();

        // 9:00 AM–12:00 PM Philippine time = 01:00–04:00 UTC (opens 15 min early).
        $this->assertSame('2026-10-13 00:45', $activity->fresh()->qr_opens_at->setTimezone('UTC')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-13 04:00', $activity->fresh()->qr_closes_at->setTimezone('UTC')->format('Y-m-d H:i'));

        $this->travelTo(Carbon::parse('2026-10-13 11:30', 'Asia/Manila')->utc());
        $this->assertNull($activity->fresh()->qrWindowMessage(), 'Works at 11:30 AM.');
        $this->travelTo(Carbon::parse('2026-10-13 12:05', 'Asia/Manila')->utc());
        $this->assertNotNull($activity->fresh()->qrWindowMessage(), 'Expired at 12:05 PM.');
    }
}
