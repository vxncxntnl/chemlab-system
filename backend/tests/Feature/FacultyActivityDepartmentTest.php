<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An activity's department is the instructor's own program — set
 * automatically, not chosen — so the QR course check matches their class.
 */
class FacultyActivityDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->campus = Campus::create(['campus_name' => 'Carmen']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Surveying Lab', 'status' => 'upcoming',
            'activity_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
        ];
    }

    public function test_the_activity_uses_the_instructors_program_even_if_another_is_sent(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus->id, 'department' => 'Civil Engineering']);

        $this->actingAs($faculty)->get('/faculty/activities/create')
            ->assertInertia(fn ($page) => $page->where('facultyDepartment', 'Civil Engineering'));

        $this->actingAs($faculty)->post('/faculty/activities', $this->payload(['department' => 'Nursing']))->assertSessionHasNoErrors();
        $this->actingAs($faculty)->post('/faculty/activities', $this->payload(['title' => 'No department sent']))->assertSessionHasNoErrors();

        $this->assertSame(['Civil Engineering', 'Civil Engineering'], Activity::orderBy('id')->pluck('department')->all());

        // Editing keeps it on the instructor's program too.
        $activity = Activity::first();
        $this->actingAs($faculty)->put("/faculty/activities/{$activity->id}", $this->payload(['department' => 'Pharmacy']))->assertSessionHasNoErrors();
        $this->assertSame('Civil Engineering', $activity->fresh()->department);
    }

    public function test_a_faculty_without_a_department_still_chooses_one(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus->id, 'department' => null]);

        $this->actingAs($faculty)->post('/faculty/activities', $this->payload())->assertSessionHasErrors('department');
        $this->actingAs($faculty)->post('/faculty/activities', $this->payload(['department' => 'Nursing']))->assertSessionHasNoErrors();

        $this->assertSame('Nursing', Activity::sole()->department);
    }

    public function test_the_borrowing_form_shows_the_faculty_program_and_campus(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus->id, 'department' => 'Civil Engineering']);

        $this->actingAs($faculty)->get('/faculty/borrowings/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('facultyProgram', 'Civil Engineering')->where('facultyCampus', 'Carmen'));
    }
}
