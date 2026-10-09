<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Campus;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\LabInventory;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\BorrowingService;
use App\Services\NotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Campus-based access: each campus (Carmen, Puerto, Iligan) has its own
 * Admin and Lab Staff, who see and manage only that campus. Carmen is the
 * main campus: its admin also sets up the other campuses' Admin/Lab Staff
 * accounts, approves faculty of campuses with no admin yet, and owns the
 * system setup. A campus without an admin is closed for borrowing.
 * Everything is enforced on the backend, including hand-made requests.
 */
class CampusAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Campus> */
    private array $campus = [];
    /** @var array<string, User> */
    private array $staff = [];
    /** @var array<string, array> */
    private array $records = [];
    private User $carmenAdmin;
    private User $puertoAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutVite();

        foreach (['Carmen', 'Puerto', 'Iligan'] as $name) {
            $this->campus[$name] = Campus::create(['campus_name' => "PHINMA COC – {$name}"]);
        }
        $this->carmenAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $this->campus['Carmen']->id, 'name' => 'Carmen Admin']);
        // Puerto has its own admin; Iligan doesn't have one yet.
        $this->puertoAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $this->campus['Puerto']->id, 'name' => 'Puerto Admin']);

        foreach ($this->campus as $name => $campus) {
            $this->staff[$name] = User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $campus->id, 'name' => "{$name} Staff"]);
            $this->records[$name] = $this->recordsAt($campus, $name);
        }
    }

    private function recordsAt(Campus $campus, string $name): array
    {
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => "Lab Storage Room – {$name}"]);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . $name, 'name' => "{$name} Beaker", 'category' => 'Glassware', 'condition' => 'good', 'is_available' => true]);
        $inventory = EquipmentInventory::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id, 'total_quantity' => 5, 'available_quantity' => 4, 'reorder_level' => 5]);
        $faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $campus->id, 'department' => 'Nursing', 'name' => "{$name} Faculty"]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $campus->id, 'student_id' => 'S-' . $name, 'course' => 'Nursing', 'name' => "{$name} Student"]);
        $borrowing = Borrowing::create(['user_id' => $student->id, 'faculty_id' => $faculty->id, 'borrow_date' => now(), 'return_date' => now()->addDays(3), 'status' => 'borrowed', 'claim_code' => 'CHEM-' . $name]);
        BorrowingEquipment::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'inventory_id' => $inventory->id, 'quantity' => 1, 'condition_on_borrow' => 'good']);
        $pending = Borrowing::create(['user_id' => $student->id, 'faculty_id' => $faculty->id, 'borrow_date' => now(), 'return_date' => now()->addDays(3), 'status' => 'pending_admin', 'claim_code' => 'CHEM-P-' . $name]);
        BorrowingEquipment::create(['borrowing_id' => $pending->id, 'equipment_id' => $equipment->id, 'inventory_id' => $inventory->id, 'quantity' => 1, 'condition_on_borrow' => 'good']);
        $report = DamageReport::create(['borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id, 'equipment_inventory_id' => $inventory->id, 'quantity' => 1, 'reported_by' => $this->staff[$name]->id, 'description' => 'Crack', 'status' => 'pending']);
        $log = MaintenanceLog::create(['equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'performed_by' => $this->staff[$name]->id, 'type' => 'inspection', 'description' => 'Check', 'maintenance_date' => now(), 'status' => 'completed']);
        $activity = Activity::create(['title' => "{$name} Lab", 'instructor_id' => $faculty->id, 'department' => 'Nursing', 'activity_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(), 'status' => 'upcoming']);

        return compact('equipment', 'inventory', 'faculty', 'student', 'borrowing', 'pending', 'report', 'log', 'activity');
    }

    private function newUser(array $overrides): array
    {
        return array_merge([
            'name' => 'New Person',
            'email' => 'new.' . uniqid() . '@example.test',
            'password' => 'Str0ng!Passw0rd#',
            'password_confirmation' => 'Str0ng!Passw0rd#',
        ], $overrides);
    }

    private function ids($response, string $key): array
    {
        return collect($response->viewData('page')['props'][$key]['data'] ?? $response->viewData('page')['props'][$key])->pluck('id')->all();
    }

    // ── Admin accounts: one per campus, set up by the main campus ───────

    public function test_the_main_admin_sets_up_other_campus_admins_one_per_campus(): void
    {
        $this->actingAs($this->carmenAdmin)->post('/admin/users', $this->newUser(['role' => 'admin', 'campus_id' => $this->campus['Iligan']->id, 'email' => 'iligan.admin@example.test']))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->campus['Iligan']->id, User::where('email', 'iligan.admin@example.test')->value('campus_id'));

        foreach (['Carmen', 'Puerto', 'Iligan'] as $name) {
            $this->actingAs($this->carmenAdmin)->post('/admin/users', $this->newUser(['role' => 'admin', 'campus_id' => $this->campus[$name]->id]))
                ->assertSessionHasErrors('campus_id');
        }
        $this->assertSame(3, User::where('role', 'admin')->count());
    }

    public function test_the_database_also_refuses_a_second_active_admin_for_a_campus(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $this->campus['Puerto']->id]);
    }

    public function test_a_campus_admin_cannot_create_admins_or_users_of_other_campuses(): void
    {
        $this->actingAs($this->puertoAdmin)->post('/admin/users', $this->newUser(['role' => 'admin', 'campus_id' => $this->campus['Iligan']->id]))
            ->assertSessionHasErrors('role');
        $this->actingAs($this->puertoAdmin)->post('/admin/users', $this->newUser(['role' => 'faculty', 'campus_id' => $this->campus['Carmen']->id, 'department' => 'Nursing']))
            ->assertSessionHasErrors('campus_id');

        $this->actingAs($this->puertoAdmin)->post('/admin/users', $this->newUser(['role' => 'faculty', 'campus_id' => $this->campus['Puerto']->id, 'department' => 'Nursing', 'email' => 'p.fac@example.test']))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->campus['Puerto']->id, User::where('email', 'p.fac@example.test')->value('campus_id'));
    }

    public function test_the_main_admin_only_sets_up_admin_and_lab_staff_at_other_campuses(): void
    {
        $this->actingAs($this->carmenAdmin)->post('/admin/users', $this->newUser(['role' => 'student', 'campus_id' => $this->campus['Puerto']->id, 'student_id' => 'S-NEW', 'course' => 'Nursing']))
            ->assertSessionHasErrors('campus_id');

        // Puerto already has lab staff; a suspended one can be replaced.
        $this->actingAs($this->carmenAdmin)->post('/admin/users', $this->newUser(['role' => 'lab_staff', 'campus_id' => $this->campus['Puerto']->id]))
            ->assertSessionHasErrors('campus_id');
        $this->actingAs($this->carmenAdmin)->post("/admin/users/{$this->staff['Puerto']->id}/suspend")->assertSessionHas('success');
        $this->actingAs($this->carmenAdmin)->post('/admin/users', $this->newUser(['role' => 'lab_staff', 'campus_id' => $this->campus['Puerto']->id, 'email' => 'p.staff2@example.test']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->carmenAdmin)->post("/admin/users/{$this->staff['Puerto']->id}/approve")->assertSessionHas('error');
    }

    public function test_nobody_changes_their_own_role_or_campus(): void
    {
        $this->actingAs($this->puertoAdmin)->put("/admin/users/{$this->puertoAdmin->id}", ['name' => 'Puerto Admin', 'email' => $this->puertoAdmin->email, 'role' => 'admin', 'campus_id' => $this->campus['Carmen']->id])
            ->assertSessionHasErrors('role');
        $this->actingAs($this->puertoAdmin)->put("/admin/users/{$this->puertoAdmin->id}", ['name' => 'Renamed', 'email' => $this->puertoAdmin->email, 'role' => 'admin'])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->campus['Puerto']->id, $this->puertoAdmin->fresh()->campus_id);
        $this->assertSame('Renamed', $this->puertoAdmin->fresh()->name);

        $student = $this->records['Carmen']['student'];
        $this->actingAs($student)->post('/complete-profile', ['role' => 'student', 'campus_id' => $this->campus['Iligan']->id, 'student_id' => 'S-X', 'course' => 'Nursing'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame($this->campus['Carmen']->id, $student->fresh()->campus_id);
    }

    public function test_each_admin_sees_only_the_users_they_manage(): void
    {
        $puerto = $this->ids($this->actingAs($this->puertoAdmin)->get('/admin/users'), 'users');
        $this->assertContains($this->records['Puerto']['student']->id, $puerto);
        $this->assertNotContains($this->records['Carmen']['student']->id, $puerto);
        $this->assertNotContains($this->carmenAdmin->id, $puerto);

        $carmen = $this->ids($this->actingAs($this->carmenAdmin)->get('/admin/users'), 'users');
        $this->assertContains($this->records['Carmen']['student']->id, $carmen);
        $this->assertContains($this->puertoAdmin->id, $carmen, 'Main admin manages other campus admins.');
        $this->assertContains($this->staff['Iligan']->id, $carmen, 'Main admin manages other campus lab staff.');
        $this->assertNotContains($this->records['Puerto']['student']->id, $carmen);

        $this->actingAs($this->puertoAdmin)->get("/admin/users/{$this->records['Carmen']['student']->id}/edit")->assertForbidden();
        $this->actingAs($this->carmenAdmin)->get("/admin/users/{$this->records['Puerto']['student']->id}/edit")->assertForbidden();
    }

    public function test_faculty_sign_ups_go_to_the_main_admin_until_the_campus_has_its_own(): void
    {
        $iliganFaculty = User::factory()->create(['role' => 'faculty', 'status' => 'pending', 'campus_id' => $this->campus['Iligan']->id, 'department' => 'Nursing']);
        $puertoFaculty = User::factory()->create(['role' => 'faculty', 'status' => 'pending', 'campus_id' => $this->campus['Puerto']->id, 'department' => 'Nursing']);

        // Iligan has no admin yet: Carmen sees and approves; Puerto handles its own.
        $carmen = $this->ids($this->actingAs($this->carmenAdmin)->get('/admin/users'), 'users');
        $this->assertContains($iliganFaculty->id, $carmen);
        $this->assertNotContains($puertoFaculty->id, $carmen);
        $this->actingAs($this->carmenAdmin)->post("/admin/users/{$puertoFaculty->id}/approve")->assertForbidden();
        $this->actingAs($this->puertoAdmin)->post("/admin/users/{$puertoFaculty->id}/approve")->assertSessionHas('success');

        // Once Iligan gets an admin, its pending faculty move there.
        $iliganAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $this->campus['Iligan']->id]);
        $this->actingAs($this->carmenAdmin)->post("/admin/users/{$iliganFaculty->id}/approve")->assertForbidden();
        $this->actingAs($iliganAdmin)->post("/admin/users/{$iliganFaculty->id}/approve")->assertSessionHas('success');
    }

    public function test_the_user_form_shows_allowed_campuses_and_taken_slots(): void
    {
        $this->actingAs($this->carmenAdmin)->get('/admin/users/create')
            ->assertInertia(fn ($page) => $page->where('isMainAdmin', true)->has('campuses', 3)
                ->where('adminByCampus.' . $this->campus['Puerto']->id, 'Puerto Admin')
                ->where('labStaffByCampus.' . $this->campus['Iligan']->id, 'Iligan Staff'));
        $this->actingAs($this->puertoAdmin)->get('/admin/users/create')
            ->assertInertia(fn ($page) => $page->where('isMainAdmin', false)->has('campuses', 1)->where('campuses.0.id', $this->campus['Puerto']->id));
    }

    // ── Admins see and manage only their own campus ─────────────────────

    public function test_each_admin_lists_and_reports_show_only_their_campus(): void
    {
        foreach (['Carmen' => $this->carmenAdmin, 'Puerto' => $this->puertoAdmin] as $name => $admin) {
            $own = $this->records[$name];
            $this->actingAs($admin);

            $this->assertEqualsCanonicalizing([$own['borrowing']->id, $own['pending']->id], $this->ids($this->get('/admin/borrowings'), 'borrowings'), $name);
            $this->assertSame([$own['report']->id], $this->ids($this->get('/admin/damage-reports'), 'reports'), $name);
            $this->assertSame([$own['equipment']->id], $this->ids($this->get('/admin/equipment'), 'equipment'), $name);
            $this->assertSame([$own['log']->id], $this->ids($this->get('/admin/maintenance'), 'maintenanceLogs'), $name);
            $this->assertSame([$own['activity']->id], $this->ids($this->get('/admin/activities'), 'activities'), $name);
            $this->assertSame([$own['inventory']->id], $this->ids($this->get('/admin/inventory'), 'inventory'), $name);
            $this->assertSame([$own['inventory']->id], $this->ids($this->get('/admin/reports/low-stock'), 'items'), $name);

            $this->get('/admin/dashboard')->assertInertia(fn ($page) => $page
                ->where('stats.pending_requests', 1)->where('stats.active_borrowings', 1)->where('stats.pending_damage_reports', 1)
                ->where('inventoryStats.total_inventory', 5)->has('recentBorrowings', 2));
            $this->get('/admin/reports/unreturned-items')->assertInertia(fn ($page) => $page->has('items', 1)->where('items.0.borrowing_id', $own['borrowing']->id));
            $this->get('/admin/reports/active-borrowers')->assertInertia(fn ($page) => $page->has('borrowers', 1)->where('borrowers.0.id', $own['student']->id));
            $this->get('/admin/reports/monthly')->assertInertia(fn ($page) => $page->where('data.0.total_transactions', 2));
            $this->get('/admin/borrowings/' . $own['pending']->id)->assertOk();
            // Sidebar badges count this campus only.
            $this->get('/admin/dashboard')->assertInertia(fn ($page) => $page->where('sidebar_badges.pending_borrowings', 1)->where('sidebar_badges.pending_damage_reports', 1));
        }
    }

    public function test_cross_campus_urls_and_ids_are_refused_for_admins(): void
    {
        foreach (['Carmen' => [$this->carmenAdmin, 'Puerto'], 'Puerto' => [$this->puertoAdmin, 'Carmen']] as [$admin, $otherName]) {
            $o = $this->records[$otherName];
            $this->actingAs($admin);

            $this->get("/admin/borrowings/{$o['borrowing']->id}")->assertForbidden();
            $this->post("/admin/borrowings/{$o['pending']->id}/approve")->assertForbidden();
            $this->post("/admin/borrowings/{$o['pending']->id}/reject", ['rejection_reason' => 'x'])->assertForbidden();
            $this->post("/admin/borrowings/{$o['borrowing']->id}/return", [])->assertForbidden();
            $this->get("/admin/damage-reports/{$o['report']->id}")->assertForbidden();
            $this->post("/admin/damage-reports/{$o['report']->id}/reject", ['reason' => 'Duplicate report'])->assertForbidden();
            $this->get("/admin/equipment/{$o['equipment']->id}")->assertForbidden();
            $this->put("/admin/equipment/{$o['equipment']->id}", ['name' => 'Hacked', 'condition' => 'good'])->assertForbidden();
            $this->get("/admin/maintenance/{$o['log']->id}")->assertForbidden();
            $this->put("/admin/inventory/{$o['inventory']->id}", ['total_quantity' => 99, 'available_quantity' => 99, 'reorder_level' => 1])->assertForbidden();
            $this->post("/admin/inventory/{$o['inventory']->id}/adjust", ['type' => 'in', 'quantity' => 5, 'reason' => 'x'])->assertForbidden();
            $this->get("/admin/activities/{$o['activity']->id}")->assertForbidden();
            $this->post("/admin/borrowings/activity/{$o['activity']->id}/approve-all")->assertForbidden();
            $this->get('/admin/borrowings/create?inventory_id=' . $o['inventory']->id)->assertInertia(fn ($page) => $page->where('preselectedInventory', null));
            $this->post('/admin/borrowings', [
                'borrow_date' => now()->toDateString(), 'return_date' => now()->addDay()->toDateString(),
                'equipment' => [['equipment_id' => $o['equipment']->id, 'inventory_id' => $o['inventory']->id, 'quantity' => 1]],
            ])->assertSessionHasErrors('equipment.0.inventory_id');

            $this->assertSame('pending_admin', $o['pending']->fresh()->status);
            $this->assertSame(5, $o['inventory']->fresh()->total_quantity);
            $this->assertSame("{$otherName} Beaker", $o['equipment']->fresh()->name);
        }
    }

    public function test_system_setup_is_main_campus_only(): void
    {
        foreach (['/admin/masterfile', '/admin/campuses', '/admin/categories', '/admin/locations', '/admin/settings', '/admin/lab-inventory'] as $url) {
            $this->actingAs($this->puertoAdmin)->get($url)->assertForbidden();
            $this->actingAs($this->carmenAdmin)->get($url)->assertOk();
        }
    }

    public function test_shared_equipment_can_only_be_renamed_by_the_main_admin(): void
    {
        $shared = $this->records['Carmen']['equipment'];
        EquipmentInventory::create(['equipment_id' => $shared->id, 'campus_id' => $this->campus['Puerto']->id, 'location_id' => Location::where('campus_id', $this->campus['Puerto']->id)->value('id'), 'total_quantity' => 2, 'available_quantity' => 2]);

        $this->actingAs($this->puertoAdmin)->get("/admin/equipment/{$shared->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->has('equipment.inventory', 1)->where('equipment.inventory.0.campus_id', $this->campus['Puerto']->id));
        $this->actingAs($this->puertoAdmin)->put("/admin/equipment/{$shared->id}", ['name' => 'Renamed', 'condition' => 'good'])->assertForbidden();
        $this->actingAs($this->carmenAdmin)->put("/admin/equipment/{$shared->id}", ['name' => 'Renamed', 'condition' => 'good'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $shared->fresh()->name);
    }

    public function test_new_equipment_goes_to_the_admins_own_campus_lab_storage_room(): void
    {
        $this->actingAs($this->puertoAdmin)->post('/admin/equipment', [
            'name' => 'Pipette', 'condition' => 'good',
            // A hand-made request naming another campus is ignored.
            'inventory' => [['campus_id' => $this->campus['Carmen']->id, 'location_id' => 1, 'total_quantity' => 3, 'reorder_level' => 1]],
        ])->assertSessionHasNoErrors();

        $inv = EquipmentInventory::whereHas('equipment', fn ($q) => $q->where('name', 'Pipette'))->sole();
        $this->assertSame($this->campus['Puerto']->id, $inv->campus_id);
        $this->assertStringStartsWith('Lab Storage Room', $inv->location->location_name);
        $this->assertSame($this->campus['Puerto']->id, $inv->location->campus_id);
    }

    // ── Lab staff: own campus only (unchanged rules) ────────────────────

    public function test_lab_staff_see_and_act_on_their_own_campus_only(): void
    {
        foreach ($this->staff as $name => $staff) {
            $own = $this->records[$name];
            $this->actingAs($staff)->get('/lab/borrowings')->assertInertia(fn ($page) => $page->has('borrowings.data', 2));
            $this->actingAs($staff)->get('/lab/dashboard')->assertInertia(fn ($page) => $page->where('stats.total_equipment', 5)->where('stats.damaged', 1));
            $this->actingAs($staff)->get("/lab/borrowings/{$own['borrowing']->id}")->assertOk();

            foreach ($this->records as $otherName => $o) {
                if ($otherName === $name) {
                    continue;
                }
                $this->actingAs($staff)->get("/lab/borrowings/{$o['borrowing']->id}")->assertForbidden();
                $this->actingAs($staff)->post("/lab/borrowings/{$o['borrowing']->id}/return", [])->assertForbidden();
                $this->actingAs($staff)->get("/lab/damage-reports/{$o['report']->id}")->assertForbidden();
                $this->actingAs($staff)->get("/lab/maintenance/{$o['log']->id}")->assertForbidden();
            }
        }
    }

    public function test_one_active_lab_staff_per_campus_also_in_the_database(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['role' => 'lab_staff', 'status' => 'active', 'campus_id' => $this->campus['Iligan']->id]);
    }

    public function test_only_carmen_lab_staff_open_the_lab_inventory_masterfile(): void
    {
        $item = LabInventory::create(['lab' => 'chemistry', 'category' => 'general', 'description' => 'Beaker', 'quantity' => 10]);
        $this->actingAs($this->staff['Carmen'])->get('/lab/lab-inventory')->assertOk();
        $this->actingAs($this->staff['Puerto'])->get('/lab/lab-inventory')->assertForbidden();
        $this->actingAs($this->staff['Iligan'])->put("/lab/lab-inventory/{$item->id}", ['quantity' => 0])->assertForbidden();
    }

    // ── Borrowing opens when a campus has its admin ─────────────────────

    public function test_borrowing_is_closed_at_a_campus_without_an_admin_until_it_gets_one(): void
    {
        $service = app(BorrowingService::class);
        $request = fn (string $name) => $service->createBorrowing(
            ['borrow_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString()],
            [['equipment_id' => $this->records[$name]['equipment']->id, 'inventory_id' => $this->records[$name]['inventory']->id, 'quantity' => 1]],
            User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $this->campus[$name]->id])->id,
        );

        $this->assertInstanceOf(Borrowing::class, $request('Carmen'));
        $this->assertInstanceOf(Borrowing::class, $request('Puerto'));

        try {
            $request('Iligan');
            $this->fail('Iligan has no admin yet — borrowing should be closed.');
        } catch (\App\Exceptions\InsufficientInventoryException $e) {
            $this->assertStringContainsString('not yet available', $e->getMessage());
        }

        // The request pages show it too.
        $this->actingAs($this->records['Iligan']['student'])->get('/student/borrowings/create')->assertOk()
            ->assertInertia(fn ($page) => $page->where('borrowingOpen', false));
        $this->actingAs($this->records['Iligan']['faculty'])->get('/faculty/borrowings/create')->assertOk()
            ->assertInertia(fn ($page) => $page->where('borrowingOpen', false));
        $this->actingAs($this->records['Carmen']['student'])->get('/student/borrowings/create')->assertOk()
            ->assertInertia(fn ($page) => $page->where('borrowingOpen', true));

        User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $this->campus['Iligan']->id]);
        $this->assertInstanceOf(Borrowing::class, $request('Iligan'));
        $this->actingAs($this->records['Iligan']['student'])->get('/student/borrowings/create')
            ->assertInertia(fn ($page) => $page->where('borrowingOpen', true));
    }

    // ── Notifications reach the right campus ────────────────────────────

    public function test_admin_and_lab_staff_notifications_go_to_the_borrowings_campus(): void
    {
        $puerto = $this->records['Puerto']['pending'];
        NotificationService::borrowingApprovedByFaculty($puerto->load('user'), $this->records['Puerto']['faculty']);
        NotificationService::borrowingApprovedByAdmin($puerto->load('user'), $this->puertoAdmin);

        $this->assertTrue(UserNotification::where('user_id', $this->puertoAdmin->id)->exists());
        $this->assertFalse(UserNotification::where('user_id', $this->carmenAdmin->id)->exists());
        $this->assertTrue(UserNotification::where('user_id', $this->staff['Puerto']->id)->exists());
        $this->assertFalse(UserNotification::where('user_id', $this->staff['Carmen']->id)->exists());

        // Iligan has no admin: the main campus admin covers.
        NotificationService::borrowingApprovedByFaculty($this->records['Iligan']['pending']->load('user'), $this->records['Iligan']['faculty']);
        $this->assertTrue(UserNotification::where('user_id', $this->carmenAdmin->id)->exists());
    }
}
