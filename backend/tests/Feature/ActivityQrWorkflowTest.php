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

    public function test_enough_stock_lets_the_request_through(): void
    {
        $this->actingAs($this->ana)->get($this->qrPath());
        $this->submit($this->ana, [$this->line($this->beakers, 3)])->assertSessionHasNoErrors();

        $this->assertSame('pending_admin', Borrowing::sole()->status);
        $this->assertSame(7, $this->beakers->fresh()->available_quantity);
    }

    public function test_exactly_enough_stock_lets_the_request_through_down_to_zero(): void
    {
        $this->beakers->update(['available_quantity' => 2]);
        $this->actingAs($this->ana)->get($this->qrPath());

        $this->submit($this->ana, [$this->line($this->beakers, 2)])->assertSessionHasNoErrors();

        $this->assertSame(1, Borrowing::count());
        $this->assertSame(0, $this->beakers->fresh()->available_quantity);
    }

    public function test_not_enough_stock_is_refused_with_a_clear_message(): void
    {
        $this->beakers->update(['available_quantity' => 1]);
        $this->actingAs($this->ana)->get($this->qrPath());

        $this->submit($this->ana, [$this->line($this->beakers, 2), $this->line($this->burners)])
            ->assertSessionHasErrors(['equipment' => 'Beaker (250 mL) is currently unavailable because there is not enough stock (only 1 left). Please lower the quantity.']);

        $this->assertSame(0, Borrowing::count(), 'Nothing approved or reserved.');
        $this->assertSame(1, $this->beakers->fresh()->available_quantity);
        $this->assertSame(5, $this->burners->fresh()->available_quantity, 'The whole request is refused, not half-saved.');
    }

    public function test_the_scan_page_names_activity_items_that_are_out_of_stock(): void
    {
        $this->beakers->update(['available_quantity' => 0]);
        $this->actingAs($this->ana)->get($this->qrPath());

        $this->actingAs($this->ana)->get(route('student.borrowings.create', ['activity_id' => $this->activity->id]))
            ->assertInertia(fn ($page) => $page->where('activityUnavailableItems', ['Beaker (250 mL)']));

        $this->submit($this->ana, [$this->line($this->beakers)])
            ->assertSessionHasErrors(['equipment' => 'Beaker (250 mL) is currently unavailable because there is not enough stock.']);
    }

    public function test_many_students_share_the_qr_until_the_stock_runs_out(): void
    {
        $this->beakers->update(['available_quantity' => 3]);
        $students = collect(range(1, 4))->map(fn ($n) => $this->student("Student {$n}", "S-1{$n}"));

        foreach ($students as $n => $student) {
            $this->actingAs($student)->get($this->qrPath());
            $response = $this->submit($student, [$this->line($this->beakers)]);
            if ($n < 3) {
                $response->assertSessionHasNoErrors();
                $this->assertSame(2 - $n, $this->beakers->fresh()->available_quantity); // 2, 1, 0
            } else {
                $response->assertSessionHasErrors(['equipment' => 'Beaker (250 mL) is currently unavailable because there is not enough stock.']);
            }
        }

        $this->assertSame(3, Borrowing::where('activity_id', $this->activity->id)->count(), 'One request per student who got stock.');
        $this->assertSame(0, $this->beakers->fresh()->available_quantity, 'Never negative.');
    }

    public function test_two_students_after_the_last_item_only_one_gets_it(): void
    {
        $this->beakers->update(['available_quantity' => 1]);
        // Both open the QR page while 1 is still shown as available.
        $this->actingAs($this->ana)->get($this->qrPath());
        $this->actingAs($this->ben)->get($this->qrPath());

        $this->submit($this->ana, [$this->line($this->beakers)])->assertSessionHasNoErrors();
        $this->submit($this->ben, [$this->line($this->beakers)])->assertSessionHasErrors('equipment');

        $this->assertSame(1, Borrowing::count());
        $this->assertSame($this->ana->id, Borrowing::sole()->user_id);
        $this->assertSame(0, $this->beakers->fresh()->available_quantity);
    }

    public function test_the_final_stock_check_runs_with_the_row_locked_when_saving(): void
    {
        // Ben's submit passed the quick check, then Ana took the last one
        // before his request was saved: the locked re-check stops him.
        $this->beakers->update(['available_quantity' => 0]);
        $this->activity->students()->attach($this->ben->id);

        try {
            app(\App\Services\BorrowingService::class)->createBorrowing([
                'faculty_id' => $this->faculty->id, 'activity_id' => $this->activity->id,
                'borrow_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
            ], [$this->line($this->beakers)], $this->ben->id, notifyFaculty: false);
            $this->fail('Expected the locked stock check to refuse the request.');
        } catch (\App\Exceptions\InsufficientInventoryException $e) {
            $this->assertStringContainsString('Beaker (250 mL) is currently unavailable', $e->getMessage());
        }

        $this->assertSame(0, Borrowing::count());
        $this->assertSame(0, $this->beakers->fresh()->available_quantity);
    }

    public function test_two_submits_at_once_by_one_student_make_only_one_request(): void
    {
        $this->activity->students()->attach($this->ana->id);
        $service = app(\App\Services\BorrowingService::class);
        $data = ['faculty_id' => $this->faculty->id, 'activity_id' => $this->activity->id, 'borrow_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString()];

        $service->createBorrowing($data, [$this->line($this->beakers)], $this->ana->id, notifyFaculty: false);
        // A different payload (so not the same-content lock) for the same activity.
        $this->expectException(\App\Exceptions\DuplicateSubmissionException::class);
        try {
            $service->createBorrowing($data, [$this->line($this->burners)], $this->ana->id, notifyFaculty: false);
        } finally {
            $this->assertSame(1, Borrowing::count());
            $this->assertSame(5, $this->burners->fresh()->available_quantity, 'Nothing reserved for the duplicate.');
        }
    }

    public function test_the_admin_can_filter_borrowings_by_activity(): void
    {
        foreach ([$this->ana, $this->ben] as $student) {
            $this->actingAs($student)->get($this->qrPath());
            $this->submit($student, [$this->line($this->beakers)])->assertSessionHasNoErrors();
        }
        Borrowing::create(['user_id' => $this->ana->id, 'borrow_date' => now(), 'return_date' => now()->addDay(), 'status' => 'pending_admin']); // walk-in

        $this->actingAs($this->admin)->get('/admin/borrowings')
            ->assertInertia(fn ($page) => $page->has('borrowings.data', 3)->where('activities.0.title', 'Titration Lab'));

        $this->actingAs($this->admin)->get("/admin/borrowings?activity_id={$this->activity->id}")
            ->assertInertia(fn ($page) => $page->has('borrowings.data', 2)->where('activityPendingCount', 2));
    }

    public function test_approve_all_approves_each_waiting_request_of_the_activity(): void
    {
        $students = collect([$this->ana, $this->ben, $this->student('Cara', 'S-3')]);
        foreach ($students as $student) {
            $this->actingAs($student)->get($this->qrPath());
            $this->submit($student, [$this->line($this->beakers), $this->line($this->burners)])->assertSessionHasNoErrors();
        }
        // Cara cancels hers; a walk-in request isn't part of the activity.
        Borrowing::where('user_id', $students[2]->id)->update(['status' => 'cancelled']);
        $walkIn = Borrowing::create(['user_id' => $this->ben->id, 'borrow_date' => now(), 'return_date' => now()->addDay(), 'status' => 'pending_admin']);

        $this->withoutDefer(); // run the after-response notifications now
        $this->actingAs($this->admin)->post("/admin/borrowings/activity/{$this->activity->id}/approve-all")
            ->assertSessionHas('success', '2 request(s) for "Titration Lab" approved.');

        $this->assertSame(['approved', 'approved', 'cancelled'], $students->map(fn ($s) => Borrowing::where('user_id', $s->id)->where('activity_id', $this->activity->id)->value('status'))->all());
        $this->assertSame('pending_admin', $walkIn->fresh()->status, 'Other requests are untouched.');
        $this->assertSame(2, Borrowing::where('activity_id', $this->activity->id)->whereNotNull('claim_code')->where('status', 'approved')->distinct()->count('claim_code'), 'Each student keeps their own claim code.');
        $this->assertSame(7, $this->beakers->fresh()->available_quantity, 'Approving doesn\'t deduct stock again.');
        foreach ([$this->ana, $this->ben] as $student) {
            $this->assertTrue(\App\Models\UserNotification::where('user_id', $student->id)->where('title', 'Borrow Request Fully Approved')->exists(), "{$student->name} is notified.");
        }

        $this->actingAs($this->admin)->post("/admin/borrowings/activity/{$this->activity->id}/approve-all")
            ->assertSessionHas('error');
    }

    public function test_only_the_admin_can_approve_all(): void
    {
        $this->actingAs($this->ana)->get($this->qrPath());
        $this->submit($this->ana, [$this->line($this->beakers)])->assertSessionHasNoErrors();

        $this->actingAs($this->faculty)->post("/admin/borrowings/activity/{$this->activity->id}/approve-all")->assertForbidden();
        $this->actingAs($this->ana)->post("/admin/borrowings/activity/{$this->activity->id}/approve-all")->assertForbidden();

        $this->assertSame('pending_admin', Borrowing::sole()->status);
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
