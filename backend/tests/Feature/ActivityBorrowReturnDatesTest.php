<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityEquipmentQr;
use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faculty set a borrow date (activity_date) and a return date on an
 * activity; requests a student makes for an activity they joined use
 * those dates, on the backend, whatever the form sends.
 */
class ActivityBorrowReturnDatesTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;
    private User $faculty;
    private User $student;
    private EquipmentInventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->campus = Campus::create(['campus_name' => 'Main Campus']);
        $this->faculty = User::factory()->create([
            'role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus->id, 'department' => 'Nursing',
        ]);
        $this->student = User::factory()->create([
            'role' => 'student', 'status' => 'active', 'campus_id' => $this->campus->id,
            'student_id' => 'S-' . uniqid(), 'course' => 'Nursing',
        ]);
        $location = Location::create(['campus_id' => $this->campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Beaker', 'condition' => 'good', 'is_available' => true]);
        $this->inventory = EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $this->campus->id, 'location_id' => $location->id,
            'total_quantity' => 5, 'available_quantity' => 5,
        ]);
    }

    private function activityPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Lab Exam', 'department' => 'Nursing', 'status' => 'upcoming',
            'activity_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'equipment_inventory_ids' => [$this->inventory->id],
        ];
    }

    private function joinedActivity(?string $returnDate): Activity
    {
        $activity = Activity::create([
            'title' => 'Lab Exam', 'instructor_id' => $this->faculty->id, 'department' => 'Nursing',
            'activity_date' => now()->addDay()->toDateString(), 'return_date' => $returnDate, 'status' => 'upcoming',
        ]);
        ActivityEquipmentQr::findOrCreateFor($activity->id, $this->inventory->id);
        $activity->students()->attach($this->student->id);

        return $activity;
    }

    private function studentRequest(Activity $activity, string $borrow, string $return)
    {
        return $this->actingAs($this->student)->post('/student/borrowings', [
            'borrow_date' => $borrow, 'return_date' => $return,
            'purpose' => 'Lab exam', 'subject' => 'Lab Exam',
            'faculty_id' => $this->faculty->id, 'activity_id' => $activity->id,
            'equipment' => [['equipment_id' => $this->inventory->equipment_id, 'inventory_id' => $this->inventory->id, 'quantity' => 1]],
        ]);
    }

    public function test_faculty_saves_a_borrow_and_return_date(): void
    {
        $this->actingAs($this->faculty)->post('/faculty/activities', $this->activityPayload())->assertSessionHasNoErrors();

        $activity = Activity::first();
        $this->assertSame(now()->addDay()->toDateString(), $activity->activity_date->toDateString());
        $this->assertSame(now()->addDays(3)->toDateString(), $activity->return_date->toDateString());
    }

    public function test_the_return_date_is_required_and_cannot_be_before_the_borrow_date(): void
    {
        $this->actingAs($this->faculty)->post('/faculty/activities', $this->activityPayload(['return_date' => '']))
            ->assertSessionHasErrors('return_date');
        $this->actingAs($this->faculty)->post('/faculty/activities', $this->activityPayload(['return_date' => now()->toDateString()]))
            ->assertSessionHasErrors('return_date');

        $this->assertSame(0, Activity::count());
    }

    public function test_the_edit_page_receives_dates_the_date_input_can_show(): void
    {
        $activity = $this->joinedActivity(now()->addDays(3)->toDateString());

        $this->actingAs($this->faculty)->get("/faculty/activities/{$activity->id}/edit")
            ->assertInertia(fn ($page) => $page
                ->where('activity.activity_date', now()->addDay()->toDateString())
                ->where('activity.return_date', now()->addDays(3)->toDateString()));
    }

    public function test_a_student_request_for_a_joined_activity_uses_the_activity_dates(): void
    {
        $activity = $this->joinedActivity(now()->addDays(3)->toDateString());

        // The form tries to send different dates.
        $this->studentRequest($activity, now()->addDays(5)->toDateString(), now()->addDays(9)->toDateString())
            ->assertSessionHasNoErrors();

        $borrowing = Borrowing::first();
        $this->assertSame(now()->addDay()->toDateString(), substr((string) $borrowing->borrow_date, 0, 10));
        $this->assertSame(now()->addDays(3)->toDateString(), substr((string) $borrowing->return_date, 0, 10));
    }

    public function test_an_older_activity_without_a_return_date_keeps_the_students_dates(): void
    {
        $activity = $this->joinedActivity(null);

        $this->studentRequest($activity, now()->addDays(2)->toDateString(), now()->addDays(4)->toDateString())
            ->assertSessionHasNoErrors();

        $this->assertSame(now()->addDays(4)->toDateString(), substr((string) Borrowing::first()->return_date, 0, 10));
    }
}
