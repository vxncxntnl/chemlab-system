<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Activity QR security without the school's enrollment records:
 * the QR works only during the activity time, Live (classroom) mode codes
 * change every minute, scanning only asks to join, the instructor approves
 * the class, other campuses are rejected, and release needs an ID check.
 */
class ActivityQrSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Campus $carmen;
    private User $faculty;
    private User $ana;
    private EquipmentInventory $beakers;
    private Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutVite();
        $this->travelTo(now()->setTime(10, 0));

        $this->carmen = Campus::create(['campus_name' => 'PHINMA COC – Carmen']);
        $location = Location::create(['campus_id' => $this->carmen->id, 'location_name' => 'Lab Storage Room – Carmen']);
        User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $this->carmen->id]);
        $this->faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->carmen->id, 'department' => 'Nursing', 'name' => 'Prof. Santos']);
        $this->ana = $this->student('Ana', 'S-1');
        $equipment = Equipment::create(['equipment_code' => 'EQ-1', 'name' => 'Beaker', 'condition' => 'good', 'is_available' => true]);
        $this->beakers = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $this->carmen->id, 'location_id' => $location->id, 'total_quantity' => 10, 'available_quantity' => 10]);

        // Today, 9:00 AM – 12:00 PM: the QR works 8:45 AM – 12:00 PM.
        $this->actingAs($this->faculty)->post('/faculty/activities', [
            'title' => 'Titration Lab', 'status' => 'upcoming', 'class_label' => 'Chem 101 – BSN1-A',
            'activity_date' => now()->toDateString(), 'return_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00', 'end_time' => '12:00',
            'equipment_inventory_ids' => [$this->beakers->id],
        ])->assertSessionHasNoErrors();
        $this->activity = Activity::sole();
    }

    private function student(string $name, string $sid, ?Campus $campus = null, string $course = 'Nursing'): User
    {
        return User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => $name, 'campus_id' => ($campus ?? $this->carmen)->id, 'student_id' => $sid, 'course' => $course]);
    }

    private function scan(User $student, ?string $code = null)
    {
        return $this->actingAs($student)->get('/qr/' . $this->activity->fresh()->qr_token . ($code ? "?c={$code}" : ''));
    }

    private function submit(User $student)
    {
        return $this->actingAs($student)->post('/student/borrowings', [
            'borrow_date' => now()->toDateString(), 'return_date' => now()->addDay()->toDateString(),
            'purpose' => 'Lab', 'subject' => 'Titration Lab', 'activity_id' => $this->activity->id,
            'equipment' => [['equipment_id' => $this->beakers->equipment_id, 'inventory_id' => $this->beakers->id, 'quantity' => 1]],
        ]);
    }

    private function joinStatus(User $student): ?string
    {
        return $this->activity->students()->where('activity_students.student_id', $student->id)->first()?->pivot->status;
    }

    public function test_the_qr_window_comes_from_the_activity_time(): void
    {
        $this->assertSame('08:45', $this->activity->qr_opens_at->format('H:i'));
        $this->assertSame('12:00', $this->activity->qr_closes_at->format('H:i'));
    }

    public function test_scanning_only_asks_to_join_and_the_instructor_approves(): void
    {
        $this->scan($this->ana)->assertRedirect(route('dashboard'))->assertSessionHas('info');
        $this->assertSame('pending', $this->joinStatus($this->ana));
        $this->assertTrue(UserNotification::where('user_id', $this->faculty->id)->exists(), 'The instructor is told students are waiting.');

        // Waiting: can't borrow, nothing created, stock untouched.
        $this->submit($this->ana)->assertSessionHasErrors('activity_id');
        $this->assertSame(0, Borrowing::count());
        $this->assertSame(10, $this->beakers->fresh()->available_quantity);

        // The instructor sees them waiting and approves.
        $this->actingAs($this->faculty)->get("/faculty/activities/{$this->activity->id}")
            ->assertInertia(fn ($page) => $page->has('waiting', 1)->where('waiting.0.student_id', 'S-1'));
        $this->actingAs($this->faculty)->post("/faculty/activities/{$this->activity->id}/students", ['decision' => 'approve', 'student_ids' => [$this->ana->id]])
            ->assertSessionHas('success');
        $this->assertSame('approved', $this->joinStatus($this->ana));
        $this->assertTrue(UserNotification::where('user_id', $this->ana->id)->where('title', 'You Can Now Borrow for Your Activity')->exists());

        $this->scan($this->ana)->assertRedirect(route('student.borrowings.create', ['activity_id' => $this->activity->id]));
        $this->submit($this->ana)->assertSessionHasNoErrors();
        $this->assertSame(1, Borrowing::count());
    }

    public function test_a_forwarded_qr_only_reaches_the_waiting_list_and_removed_students_stay_out(): void
    {
        $friend = $this->student('Friend', 'S-99');
        $this->scan($friend);
        $this->actingAs($this->faculty)->post("/faculty/activities/{$this->activity->id}/students", ['decision' => 'reject', 'student_ids' => [$friend->id]]);

        $this->scan($friend)->assertRedirect(route('dashboard'))->assertSessionHas('error');
        $this->assertSame('rejected', $this->joinStatus($friend));
        $this->submit($friend)->assertSessionHasErrors('activity_id');
        $this->assertSame(0, Borrowing::count());
    }

    public function test_approve_all_and_only_the_instructor_decides(): void
    {
        $ben = $this->student('Ben', 'S-2');
        $this->scan($this->ana);
        $this->scan($ben);

        // Another faculty member or the student themself can't approve.
        $other = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->carmen->id, 'department' => 'Nursing']);
        $this->actingAs($other)->post("/faculty/activities/{$this->activity->id}/students", ['decision' => 'approve', 'student_ids' => [$this->ana->id]])->assertForbidden();
        $this->actingAs($this->ana)->post("/faculty/activities/{$this->activity->id}/students", ['decision' => 'approve', 'student_ids' => [$this->ana->id]])->assertForbidden();
        $this->assertSame('pending', $this->joinStatus($this->ana));

        $this->actingAs($this->faculty)->post("/faculty/activities/{$this->activity->id}/students", ['decision' => 'approve', 'student_ids' => [$this->ana->id, $ben->id]]);
        $this->assertSame('approved', $this->joinStatus($this->ana));
        $this->assertSame('approved', $this->joinStatus($ben));
    }

    public function test_the_qr_only_works_during_the_activity_time_and_can_be_extended(): void
    {
        $this->travelTo(now()->setTime(8, 30));
        $this->scan($this->ana)->assertSessionHas('error');
        $this->assertNull($this->joinStatus($this->ana));

        $this->travelTo(now()->setTime(12, 5));
        $this->scan($this->ana)->assertSessionHas('error');
        $this->assertNull($this->joinStatus($this->ana), 'An expired QR (e.g. a screenshot shared later) does nothing.');

        // Running late: the instructor extends it.
        $this->actingAs($this->faculty)->post("/faculty/activities/{$this->activity->id}/qr/extend", ['minutes' => 30])->assertSessionHas('success');
        $this->scan($this->ana)->assertRedirect(route('dashboard'));
        $this->assertSame('pending', $this->joinStatus($this->ana));
    }

    public function test_a_new_qr_makes_the_old_one_stop_working(): void
    {
        $oldToken = $this->activity->qr_token;
        $this->actingAs($this->faculty)->post("/faculty/activities/{$this->activity->id}/qr/regenerate")->assertSessionHas('success');

        $this->actingAs($this->ana)->get("/qr/{$oldToken}")->assertNotFound();
        $this->assertNotSame($oldToken, $this->activity->fresh()->qr_token);
    }

    public function test_live_mode_only_accepts_the_code_shown_on_screen(): void
    {
        $this->actingAs($this->faculty)->post("/faculty/activities/{$this->activity->id}/qr/live-mode", ['live' => true]);
        $activity = $this->activity->fresh();

        // No code (e.g. the group chat link) or an old picture: refused.
        $this->scan($this->ana)->assertSessionHas('error');
        $oldCode = $activity->liveCode();
        $this->travel(3)->minutes();
        $this->scan($this->ana, $oldCode)->assertSessionHas('error');
        $this->assertNull($this->joinStatus($this->ana));

        // The code on screen now (and the one just before it) works.
        $this->scan($this->ana, $activity->fresh()->liveCode(-1))->assertRedirect(route('dashboard'));
        $this->assertSame('pending', $this->joinStatus($this->ana));

        $this->actingAs($this->faculty)->get("/faculty/activities/{$this->activity->id}/qr/live-code")
            ->assertOk()->assertJsonPath('waiting', 1)->assertJsonPath('live', true);
        $this->actingAs($this->ana)->get("/faculty/activities/{$this->activity->id}/qr/live-code")->assertForbidden();
    }

    public function test_other_campus_and_other_course_are_rejected_without_joining(): void
    {
        $puerto = Campus::create(['campus_name' => 'PHINMA COC – Puerto']);
        $outsider = $this->student('Pia', 'P-1', $puerto);
        $crim = $this->student('Carl', 'S-3', null, 'Criminology');

        $this->scan($outsider)->assertRedirect(route('dashboard'))->assertSessionHas('error');
        $this->scan($crim)->assertRedirect(route('dashboard'))->assertSessionHas('error');
        $this->assertNull($this->joinStatus($outsider));
        $this->assertNull($this->joinStatus($crim));
    }

    public function test_students_approved_before_for_the_same_class_are_let_in(): void
    {
        $this->scan($this->ana);
        $this->actingAs($this->faculty)->post("/faculty/activities/{$this->activity->id}/students", ['decision' => 'approve', 'student_ids' => [$this->ana->id]]);

        // Next activity, same class label (any letter case): Ana goes straight in; a new face waits.
        $next = Activity::create(['title' => 'Lab 2', 'instructor_id' => $this->faculty->id, 'department' => 'Nursing', 'class_label' => 'chem 101 – bsn1-a', 'activity_date' => now()->toDateString(), 'return_date' => now()->addDay()->toDateString(), 'status' => 'upcoming']);
        $ben = $this->student('Ben', 'S-2');
        $this->actingAs($this->ana)->get("/qr/{$next->qr_token}")->assertRedirect(route('student.borrowings.create', ['activity_id' => $next->id]));
        $this->actingAs($ben)->get("/qr/{$next->qr_token}")->assertRedirect(route('dashboard'));

        // A different class of the same instructor: not remembered.
        $other = Activity::create(['title' => 'Lab X', 'instructor_id' => $this->faculty->id, 'department' => 'Nursing', 'class_label' => 'Chem 101 – BSN1-B', 'activity_date' => now()->toDateString(), 'return_date' => now()->addDay()->toDateString(), 'status' => 'upcoming']);
        $this->actingAs($this->ana)->get("/qr/{$other->qr_token}")->assertRedirect(route('dashboard'));
        $this->assertSame('pending', $other->students()->where('activity_students.student_id', $this->ana->id)->first()->pivot->status);
    }

    public function test_release_needs_the_student_number_from_the_id_and_the_check(): void
    {
        $labStaff = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->carmen->id]);
        $borrowing = Borrowing::create(['user_id' => $this->ana->id, 'borrow_date' => now(), 'return_date' => now()->addDay(), 'status' => 'approved', 'claim_code' => 'CHEM-ID-1']);
        BorrowingEquipment::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $this->beakers->equipment_id, 'inventory_id' => $this->beakers->id, 'quantity' => 1, 'condition_on_borrow' => 'good']);
        $release = fn (array $data) => $this->actingAs($labStaff)->post("/lab/borrowings/{$borrowing->id}/release", ['claim_code' => 'CHEM-ID-1'] + $data);

        $release(['student_id' => 'S-1'])->assertSessionHasErrors('id_checked');
        $release(['student_id' => 'S-2', 'id_checked' => '1'])->assertSessionHas('error');
        $this->assertSame('approved', $borrowing->fresh()->status);

        $release(['student_id' => ' s-1 ', 'id_checked' => '1'])->assertSessionHas('success');
        $this->assertSame('borrowed', $borrowing->fresh()->status);
    }
}
