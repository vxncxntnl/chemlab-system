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
 * An activity is for students of its department's course: a Criminology
 * student scanning a shared Nursing activity QR can't borrow through it.
 */
class QrActivityCourseRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;
    private User $faculty;
    private EquipmentInventory $inventory;
    private Activity $activity;
    private ActivityEquipmentQr $qr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campus = Campus::create(['campus_name' => 'Main Campus']);
        $this->faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus->id, 'department' => 'Nursing']);
        $location = Location::create(['campus_id' => $this->campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Beaker', 'condition' => 'good', 'is_available' => true]);
        $this->inventory = EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $this->campus->id, 'location_id' => $location->id,
            'total_quantity' => 5, 'available_quantity' => 5,
        ]);
        $this->activity = Activity::create([
            'title' => 'Lab Exam', 'instructor_id' => $this->faculty->id, 'department' => 'Nursing',
            'activity_date' => now()->addDay()->toDateString(), 'status' => 'upcoming',
        ]);
        $this->qr = ActivityEquipmentQr::findOrCreateFor($this->activity->id, $this->inventory->id);
    }

    private function student(string $course): User
    {
        return User::factory()->create([
            'role' => 'student', 'status' => 'active', 'campus_id' => $this->campus->id,
            'student_id' => 'S-' . uniqid(), 'course' => $course,
        ]);
    }

    public function test_a_student_from_another_course_is_blocked_from_the_qr(): void
    {
        $outsider = $this->student('Criminology');

        $this->actingAs($outsider)->get('/qr/' . $this->qr->token)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'only for Nursing students') && str_contains($m, 'Your course is Criminology'));

        $this->assertFalse($this->activity->students()->where('activity_students.student_id', $outsider->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $outsider->id, 'action' => 'QR Scan Blocked — Different Course']);
    }

    public function test_a_student_from_the_same_course_can_use_the_qr(): void
    {
        $nursing = $this->student('Nursing');

        $this->actingAs($nursing)->get('/qr/' . $this->qr->token)
            ->assertRedirect(route('student.borrowings.create', ['inventory_id' => $this->inventory->id, 'activity_id' => $this->activity->id]));

        $this->assertTrue($this->activity->students()->where('activity_students.student_id', $nursing->id)->exists());
    }

    public function test_another_course_cannot_borrow_for_the_activity_by_submitting_it_directly(): void
    {
        $outsider = $this->student('Criminology');

        $this->actingAs($outsider)->post('/student/borrowings', [
            'borrow_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
            'purpose' => 'Lab', 'subject' => 'Lab Exam', 'faculty_id' => $this->faculty->id, 'activity_id' => $this->activity->id,
            'equipment' => [['equipment_id' => $this->inventory->equipment_id, 'inventory_id' => $this->inventory->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('activity_id');

        $this->assertSame(0, Borrowing::count());
    }

    public function test_course_matching_ignores_letter_case_and_spaces(): void
    {
        $this->assertTrue($this->activity->allowsCourse(' nursing '));
        $this->assertFalse($this->activity->allowsCourse('Pharmacy'));
    }
}
