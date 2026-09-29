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

class QrRosterAndCourseRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private function makeInstructor(Campus $campus): User
    {
        return User::factory()->create([
            'role' => 'faculty',
            'status' => 'active',
            'campus_id' => $campus->id,
            'department' => 'Science and Technology',
        ]);
    }

    private function makeStudent(Campus $campus, string $course = 'Computer Engineering'): User
    {
        return User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'campus_id' => $campus->id,
            'student_id' => 'S-' . uniqid(),
            'course' => $course,
        ]);
    }

    private function makeInventory(Campus $campus, ?string $courseRestriction = null, int $available = 5): EquipmentInventory
    {
        $location = Location::create([
            'campus_id' => $campus->id,
            'location_name' => 'Main Lab',
        ]);

        $equipment = Equipment::create([
            'equipment_code' => 'EQ-' . uniqid(),
            'name' => 'Test Microscope',
            'course_restriction' => $courseRestriction,
            'condition' => 'good',
            'is_available' => true,
        ]);

        return EquipmentInventory::create([
            'equipment_id' => $equipment->id,
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'total_quantity' => $available,
            'available_quantity' => $available,
        ]);
    }

    public function test_qr_scan_from_same_campus_student_auto_enrolls_and_links_activity(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $instructor = $this->makeInstructor($campus);
        $student = $this->makeStudent($campus);

        $activity = Activity::create([
            'title' => 'Chem Lab Session',
            'instructor_id' => $instructor->id,
            'department' => 'Science and Technology',
            'activity_date' => now()->addDay(),
            'status' => 'upcoming',
        ]);

        $inventory = $this->makeInventory($campus);

        $qr = ActivityEquipmentQr::create([
            'activity_id' => $activity->id,
            'equipment_inventory_id' => $inventory->id,
            'token' => 'same-campus-token',
        ]);

        $response = $this->actingAs($student)->get('/qr/' . $qr->token);

        $response->assertRedirect(route('student.borrowings.create', [
            'inventory_id' => $inventory->id,
            'activity_id' => $activity->id,
        ]));

        $this->assertTrue(
            $activity->students()->where('activity_students.student_id', $student->id)->exists(),
            'Same-campus student should be auto-enrolled into the activity roster.'
        );
    }

    public function test_qr_scan_from_different_campus_student_skips_auto_enroll_and_activity_link(): void
    {
        $instructorCampus = Campus::create(['campus_name' => 'Main Campus']);
        $otherCampus = Campus::create(['campus_name' => 'Satellite Campus']);

        $instructor = $this->makeInstructor($instructorCampus);
        $outsideStudent = $this->makeStudent($otherCampus);

        $activity = Activity::create([
            'title' => 'Chem Lab Session',
            'instructor_id' => $instructor->id,
            'department' => 'Science and Technology',
            'activity_date' => now()->addDay(),
            'status' => 'upcoming',
        ]);

        $inventory = $this->makeInventory($instructorCampus);

        $qr = ActivityEquipmentQr::create([
            'activity_id' => $activity->id,
            'equipment_inventory_id' => $inventory->id,
            'token' => 'cross-campus-token',
        ]);

        $response = $this->actingAs($outsideStudent)->get('/qr/' . $qr->token);

        // No activity_id in the redirect — falls back to a normal, non-auto-approved request.
        $response->assertRedirect(route('student.borrowings.create', [
            'inventory_id' => $inventory->id,
        ]));

        $this->assertFalse(
            $activity->students()->where('activity_students.student_id', $outsideStudent->id)->exists(),
            'Cross-campus student should NOT be auto-enrolled into the activity roster.'
        );
    }

    public function test_store_rejects_borrowing_when_student_course_not_in_restriction(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $instructor = $this->makeInstructor($campus);
        $student = $this->makeStudent($campus, course: 'Computer Engineering');
        $inventory = $this->makeInventory($campus, courseRestriction: 'Nursing');

        $response = $this->actingAs($student)->post('/student/borrowings', [
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'purpose' => 'Lab exercise',
            'subject' => 'Biology 101',
            'faculty_id' => $instructor->id,
            'equipment' => [
                [
                    'equipment_id' => $inventory->equipment_id,
                    'inventory_id' => $inventory->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('equipment');
        $this->assertSame(0, Borrowing::count(), 'No borrowing should be created for a restricted item outside the student\'s course.');
    }

    public function test_store_allows_borrowing_when_student_course_matches_restriction(): void
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $instructor = $this->makeInstructor($campus);
        $student = $this->makeStudent($campus, course: 'Nursing');
        $inventory = $this->makeInventory($campus, courseRestriction: 'Nursing');

        $response = $this->actingAs($student)->post('/student/borrowings', [
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'purpose' => 'Lab exercise',
            'subject' => 'Biology 101',
            'faculty_id' => $instructor->id,
            'equipment' => [
                [
                    'equipment_id' => $inventory->equipment_id,
                    'inventory_id' => $inventory->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors('equipment');
        $this->assertSame(1, Borrowing::count(), 'Borrowing should be created when the student\'s course matches the restriction.');
    }
}
