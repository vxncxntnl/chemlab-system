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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * One QR code per lab activity: students scan it, get a request pre-filled
 * with all the activity's equipment, and each submits their own request,
 * which the admin approves as one transaction. The admin's Borrowings list
 * shows where each request came from.
 */
class ActivityQrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;
    private User $faculty;
    private User $admin;
    private User $ana;
    private User $ben;
    private EquipmentInventory $beakers;
    private EquipmentInventory $burners;
    private EquipmentInventory $microscopes; // not part of the activity
    private Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutVite();
        $this->campus = Campus::create(['campus_name' => 'Carmen']);
        $location = Location::create(['campus_id' => $this->campus->id, 'location_name' => 'Chem Lab 01']);
        $this->faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus->id, 'department' => 'Nursing']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->ana = $this->student('Ana', 'S-1');
        $this->ben = $this->student('Ben', 'S-2');
        $this->beakers = $this->stock($location, 'Beaker (250 mL)', 10);
        $this->burners = $this->stock($location, 'Bunsen Burner', 5);
        $this->microscopes = $this->stock($location, 'Microscope', 3);

        // The instructor creates the activity and selects 2 items.
        $this->actingAs($this->faculty)->post('/faculty/activities', [
            'title' => 'Titration Lab', 'status' => 'upcoming',
            'activity_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
            'equipment_inventory_ids' => [$this->beakers->id, $this->burners->id],
        ])->assertSessionHasNoErrors();
        $this->activity = Activity::sole();
        $this->post('/logout');
    }

    private function student(string $name, string $sid): User
    {
        return User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => $name, 'campus_id' => $this->campus->id, 'student_id' => $sid, 'course' => 'Nursing']);
    }

    private function stock(Location $location, string $name, int $qty): EquipmentInventory
    {
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => $name, 'condition' => 'good', 'is_available' => true]);

        return EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $this->campus->id, 'location_id' => $location->id, 'total_quantity' => $qty, 'available_quantity' => $qty]);
    }

    private function qrPath(): string
    {
        return '/qr/' . $this->activity->fresh()->qr_token;
    }

    private function line(EquipmentInventory $inv, int $qty = 1): array
    {
        return ['equipment_id' => $inv->equipment_id, 'inventory_id' => $inv->id, 'quantity' => $qty];
    }

    private function submit(User $student, array $items, array $extra = [])
    {
        return $this->actingAs($student)->post('/student/borrowings', [
            'borrow_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
            'purpose' => 'Titration', 'subject' => 'Titration Lab',
            // No instructor chosen: it comes from the activity.
            'activity_id' => $this->activity->id, 'equipment' => $items,
        ] + $extra);
    }

    public function test_an_activity_gets_one_qr_code_shown_to_its_instructor_but_not_listed_to_students(): void
    {
        $token = $this->activity->fresh()->qr_token;
        $this->assertNotEmpty($token);
        $this->assertSame(2, $this->activity->equipmentQrCodes()->count(), 'The selected equipment list is kept.');

        $this->actingAs($this->faculty)->get("/faculty/activities/{$this->activity->id}")
            ->assertInertia(fn ($page) => $page->where('qrUrl', url("/qr/{$token}"))->missing('activity.qr_token'));
    }

    public function test_scanning_the_qr_opens_a_request_prefilled_with_all_the_activity_equipment(): void
    {
        $this->actingAs($this->ana)->get($this->qrPath())
            ->assertRedirect(route('student.borrowings.create', ['activity_id' => $this->activity->id]));

        $this->assertTrue($this->activity->students()->where('activity_students.student_id', $this->ana->id)->exists());

        $this->actingAs($this->ana)->get(route('student.borrowings.create', ['activity_id' => $this->activity->id]))
            ->assertInertia(fn ($page) => $page->where('activityInventoryIds', [$this->beakers->id, $this->burners->id]));
        $this->assertSame(1, $this->activity->fresh()->qr_scan_count);
    }

    public function test_multiple_students_each_get_their_own_request_under_the_same_activity(): void
    {
        foreach ([$this->ana, $this->ben] as $student) {
            $this->actingAs($student)->get($this->qrPath());
            $this->submit($student, [$this->line($this->beakers, 2), $this->line($this->burners)])->assertSessionHasNoErrors();
        }

        $requests = Borrowing::where('activity_id', $this->activity->id)->get();
        $this->assertCount(2, $requests, 'One separate transaction per student.');
        $this->assertEqualsCanonicalizing([$this->ana->id, $this->ben->id], $requests->pluck('user_id')->all());
        foreach ($requests as $request) {
            $this->assertSame('pending_admin', $request->status, 'Pre-approved by the instructor via the activity.');
            $this->assertSame(2, $request->borrowingEquipment()->count(), 'Both items in one request.');
        }
        $this->assertSame(6, $this->beakers->fresh()->available_quantity);
        $this->assertSame(3, $this->burners->fresh()->available_quantity);
    }

    public function test_the_instructor_comes_from_the_activity_and_cannot_be_changed(): void
    {
        $otherFaculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $this->campus->id]);
        $this->actingAs($this->ana)->get($this->qrPath());
        $this->actingAs($this->ana)->get(route('student.borrowings.create', ['activity_id' => $this->activity->id]))
            ->assertInertia(fn ($page) => $page->where('activities.0.instructor.name', $this->faculty->name));

        $this->submit($this->ana, [$this->line($this->beakers)], ['faculty_id' => $otherFaculty->id])->assertSessionHasNoErrors();

        $this->assertSame($this->faculty->id, Borrowing::sole()->faculty_id);
    }

    public function test_a_walk_in_request_still_needs_a_faculty(): void
    {
        $this->actingAs($this->ana)->post('/student/borrowings', [
            'borrow_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
            'purpose' => 'Practice', 'subject' => 'Chem', 'equipment' => [$this->line($this->beakers)],
        ])->assertSessionHasErrors('faculty_id');
    }

    public function test_the_admin_approves_one_students_whole_request_without_touching_the_others(): void
    {
        foreach ([$this->ana, $this->ben] as $student) {
            $this->actingAs($student)->get($this->qrPath());
            $this->submit($student, [$this->line($this->beakers), $this->line($this->burners)])->assertSessionHasNoErrors();
        }
        $anaRequest = Borrowing::where('user_id', $this->ana->id)->sole();
        $benRequest = Borrowing::where('user_id', $this->ben->id)->sole();

        $this->actingAs($this->admin)->post("/admin/borrowings/{$anaRequest->id}/approve")->assertSessionHas('success');

        $this->assertSame('approved', $anaRequest->fresh()->status, 'All of Ana\'s items, in one action.');
        $this->assertSame('pending_admin', $benRequest->fresh()->status, 'Ben\'s request is not approved automatically.');
        $this->assertSame(8, $this->beakers->fresh()->available_quantity, 'Approval does not deduct stock again.');
    }

    public function test_the_same_student_cannot_create_a_duplicate_request_for_the_activity(): void
    {
        $this->actingAs($this->ana)->get($this->qrPath());
        $this->submit($this->ana, [$this->line($this->beakers)])->assertSessionHasNoErrors();

        $this->actingAs($this->ana)->get($this->qrPath()); // scanning again
        $this->submit($this->ana, [$this->line($this->burners)])->assertSessionHasErrors('activity_id');

        $this->assertSame(1, Borrowing::where('user_id', $this->ana->id)->count());
        $this->assertSame(1, $this->activity->students()->count(), 'Scanning twice doesn\'t duplicate the roster.');
    }

    public function test_students_cannot_add_equipment_the_instructor_did_not_select(): void
    {
        $this->actingAs($this->ana)->get($this->qrPath());

        $this->submit($this->ana, [$this->line($this->beakers), $this->line($this->microscopes)])->assertSessionHasErrors('equipment');

        $this->assertSame(0, Borrowing::count());
        $this->assertSame(3, $this->microscopes->fresh()->available_quantity);
    }

    public function test_a_student_who_did_not_scan_the_qr_cannot_borrow_for_the_activity(): void
    {
        $this->submit($this->ana, [$this->line($this->beakers)])->assertSessionHasErrors('activity_id');
        $this->assertSame(0, Borrowing::count());
    }

    public function test_changing_the_activity_equipment_updates_what_new_scans_get(): void
    {
        $this->actingAs($this->faculty)->put("/faculty/activities/{$this->activity->id}", [
            'title' => 'Titration Lab', 'status' => 'upcoming',
            'activity_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
            'equipment_inventory_ids' => [$this->burners->id, $this->microscopes->id],
        ])->assertSessionHasNoErrors();
        $token = $this->activity->fresh()->qr_token;

        $this->actingAs($this->ana)->get("/qr/{$token}");
        $this->actingAs($this->ana)->get(route('student.borrowings.create', ['activity_id' => $this->activity->id]))
            ->assertInertia(fn ($page) => $page->where('activityInventoryIds', [$this->burners->id, $this->microscopes->id]));
        $this->assertSame($token, $this->activity->fresh()->qr_token, 'Same QR code, updated list.');
    }

    public function test_a_guest_signs_in_with_a_password_and_continues_to_the_activity(): void
    {
        $this->get($this->qrPath())->assertRedirect(route('login'));

        $this->post('/login', ['email' => $this->ana->email, 'password' => 'password'])
            ->assertRedirect(route('student.borrowings.create', ['activity_id' => $this->activity->id]));
    }

    public function test_old_per_item_qr_codes_still_work(): void
    {
        $itemQr = ActivityEquipmentQr::where('activity_id', $this->activity->id)->where('equipment_inventory_id', $this->beakers->id)->sole();

        $this->actingAs($this->ana)->get('/qr/' . $itemQr->token)
            ->assertRedirect(route('student.borrowings.create', ['inventory_id' => $this->beakers->id, 'activity_id' => $this->activity->id]));
    }

    public function test_the_admin_list_shows_the_activity_source_and_walk_in_requests(): void
    {
        $this->actingAs($this->ana)->get($this->qrPath());
        $this->submit($this->ana, [$this->line($this->beakers)])->assertSessionHasNoErrors();
        $walkIn = Borrowing::create(['user_id' => $this->ben->id, 'borrow_date' => now(), 'return_date' => now()->addDay(), 'status' => 'pending_admin']);

        $this->actingAs($this->admin)->get('/admin/borrowings')
            ->assertInertia(fn ($page) => $page->has('borrowings.data', 2));
        $rows = collect($this->actingAs($this->admin)->get('/admin/borrowings')->viewData('page')['props']['borrowings']['data'])->keyBy('id');

        $qrRow = $rows->firstWhere('user_id', $this->ana->id);
        $this->assertSame('Titration Lab', $qrRow['activity']['title']);
        $this->assertNull($rows[$walkIn->id]['activity'], 'A direct request has no activity.');

        $this->actingAs($this->admin)->get("/admin/borrowings/{$qrRow['id']}")
            ->assertInertia(fn ($page) => $page
                ->where('borrowing.activity.title', 'Titration Lab')
                ->where('borrowing.activity.instructor.name', $this->faculty->name));
    }
}
