<?php

namespace Tests\Feature;

use App\Mail\BorrowingStatusMail;
use App\Models\Activity;
use App\Models\ActivityEquipmentQr;
use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Deleting an activity used to only unlink its requests (activity_id ON
 * DELETE SET NULL), leaving them pending with no activity behind them.
 * Activities with requests are now cancelled instead, which closes out
 * their unreleased requests properly.
 */
class ActivityCancellationTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;
    private User $faculty;
    private User $student;
    private EquipmentInventory $inventory;
    private Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->campus = Campus::create(['campus_name' => 'Main Campus']);
        $this->faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus->id, 'department' => 'Nursing', 'name' => 'Prof. Cruz']);
        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $this->campus->id, 'student_id' => 'S-1', 'course' => 'Nursing']);
        $location = Location::create(['campus_id' => $this->campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Beaker', 'condition' => 'good', 'is_available' => true]);
        $this->inventory = EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $this->campus->id, 'location_id' => $location->id,
            'total_quantity' => 5, 'available_quantity' => 5,
        ]);
        $this->activity = Activity::create([
            'title' => 'Lab Exam', 'instructor_id' => $this->faculty->id, 'department' => 'Nursing',
            'activity_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(), 'status' => 'upcoming',
        ]);
    }

    /** A request with one item reserved from stock, as creation does. */
    private function request(string $status, ?User $student = null): Borrowing
    {
        $borrowing = Borrowing::create([
            'user_id' => ($student ?? $this->student)->id, 'faculty_id' => $this->faculty->id, 'activity_id' => $this->activity->id,
            'borrow_date' => now()->addDay(), 'return_date' => now()->addDays(2), 'status' => $status,
            'claim_code' => 'CHEM-REQ-TEST-' . uniqid(),
        ]);
        BorrowingEquipment::create([
            'borrowing_id' => $borrowing->id, 'equipment_id' => $this->inventory->equipment_id,
            'inventory_id' => $this->inventory->id, 'quantity' => 1, 'condition_on_borrow' => 'good',
        ]);
        $this->inventory->decrement('available_quantity');

        return $borrowing;
    }

    public function test_cancelling_an_activity_cancels_its_unreleased_requests_and_returns_stock(): void
    {
        Mail::fake();
        $requests = [
            $this->request('pending_faculty'),
            $this->request('pending_admin', User::factory()->create(['role' => 'student', 'status' => 'active'])),
            $this->request('approved', User::factory()->create(['role' => 'student', 'status' => 'active'])),
        ];
        $this->assertSame(2, $this->inventory->fresh()->available_quantity);

        $this->actingAs($this->faculty)->post("/faculty/activities/{$this->activity->id}/cancel")->assertSessionHas('success');

        $this->assertSame('cancelled', $this->activity->fresh()->status);
        foreach ($requests as $request) {
            $this->assertSame('cancelled', $request->fresh()->status);
            $this->assertSame($this->activity->id, $request->fresh()->activity_id, 'The link to the activity is kept.');
        }
        $this->assertSame(5, $this->inventory->fresh()->available_quantity);
        Mail::assertSent(BorrowingStatusMail::class, fn ($m) => $m->hasTo($this->student->email)
            && str_contains($m->body, 'because the activity "Lab Exam" was cancelled by Prof. Cruz'));
    }

    public function test_an_activity_cannot_be_cancelled_while_equipment_is_still_out(): void
    {
        $out = $this->request('borrowed');
        $pending = $this->request('pending_admin', User::factory()->create(['role' => 'student', 'status' => 'active']));

        $this->actingAs($this->faculty)->post("/faculty/activities/{$this->activity->id}/cancel")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'must be returned'));

        $this->assertSame('upcoming', $this->activity->fresh()->status);
        $this->assertSame('pending_admin', $pending->fresh()->status);
        $this->assertSame('borrowed', $out->fresh()->status);
    }

    public function test_an_activity_with_requests_cannot_be_deleted(): void
    {
        $this->request('pending_admin');

        $this->actingAs($this->faculty)->delete("/faculty/activities/{$this->activity->id}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Use Cancel Activity instead'));

        $this->assertNotNull(Activity::find($this->activity->id));
    }

    public function test_an_activity_without_requests_can_still_be_deleted(): void
    {
        $this->actingAs($this->faculty)->delete("/faculty/activities/{$this->activity->id}")->assertSessionHas('success');

        $this->assertNull(Activity::find($this->activity->id));
    }

    public function test_the_admin_can_cancel_an_activity_too(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $request = $this->request('pending_admin');

        $this->actingAs($admin)->post("/admin/activities/{$this->activity->id}/cancel")->assertSessionHas('success');
        $this->actingAs($admin)->delete("/admin/activities/{$this->activity->id}")->assertSessionHas('error');

        $this->assertSame('cancelled', $request->fresh()->status);
    }

    public function test_a_cancelled_activitys_qr_code_stops_working(): void
    {
        $qr = ActivityEquipmentQr::findOrCreateFor($this->activity->id, $this->inventory->id);
        $this->activity->update(['status' => 'cancelled']);

        $this->actingAs($this->student)->get('/qr/' . $qr->token)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'was cancelled'));

        $this->assertFalse($this->activity->students()->where('activity_students.student_id', $this->student->id)->exists());
    }

    public function test_a_student_cannot_borrow_for_a_cancelled_activity_from_an_open_form(): void
    {
        ActivityEquipmentQr::findOrCreateFor($this->activity->id, $this->inventory->id);
        $this->activity->students()->attach($this->student->id);
        $this->activity->update(['status' => 'cancelled']);

        $this->actingAs($this->student)->post('/student/borrowings', [
            'borrow_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
            'purpose' => 'Lab', 'subject' => 'Lab Exam', 'faculty_id' => $this->faculty->id, 'activity_id' => $this->activity->id,
            'equipment' => [['equipment_id' => $this->inventory->equipment_id, 'inventory_id' => $this->inventory->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('activity_id');

        $this->assertSame(0, Borrowing::count());
    }

    public function test_a_cancelled_activity_can_no_longer_be_edited(): void
    {
        $this->activity->update(['status' => 'cancelled']);

        $this->actingAs($this->faculty)->get("/faculty/activities/{$this->activity->id}/edit")
            ->assertRedirect(route('faculty.activities.show', $this->activity))
            ->assertSessionHas('error');
    }

    public function test_the_faculty_can_cancel_a_qr_request_waiting_for_the_admin(): void
    {
        Mail::fake();
        $request = $this->request('pending_admin');

        $this->actingAs($this->faculty)->post("/faculty/borrowings/{$request->id}/cancel")->assertSessionHas('success');

        $this->assertSame('cancelled', $request->fresh()->status);
        $this->assertSame(5, $this->inventory->fresh()->available_quantity);
        Mail::assertSent(BorrowingStatusMail::class, fn ($m) => $m->hasTo($this->student->email) && str_contains($m->body, 'cancelled by Prof. Cruz'));
    }

    public function test_the_faculty_cannot_cancel_a_request_the_admin_already_approved(): void
    {
        $request = $this->request('approved');

        $this->actingAs($this->faculty)->post("/faculty/borrowings/{$request->id}/cancel")->assertSessionHas('error');

        $this->assertSame('approved', $request->fresh()->status);
    }
}
