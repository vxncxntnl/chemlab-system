<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Admin > Users > Edit could save a new role without the profile fields
 * that role's features depend on (campus for lab staff/faculty/students,
 * department for faculty, student ID/course for students), left the old
 * role's student ID/course behind on converted accounts, and had no Lab
 * Staff option at all. These walk every role-to-role change through the
 * real admin endpoint, then sign in as the changed account and open every
 * page of its new role.
 */
class UserRoleChangeTest extends TestCase
{
    use RefreshDatabase;

    private const PREFIX = ['admin' => 'admin', 'lab_staff' => 'lab', 'faculty' => 'faculty', 'student' => 'student'];

    // Uses PostgreSQL-only SQL (EXTRACT(...)::int), which the in-memory
    // SQLite test database can't run — unrelated to roles.
    private const POSTGRES_ONLY_PAGES = ['/admin/reports/monthly'];

    private Campus $campus;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->campus = Campus::create(['campus_name' => 'Main Campus']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    /** Profile fields a complete account of each role has. */
    private function roleFields(string $role): array
    {
        return match ($role) {
            'student'   => ['campus_id' => $this->campus->id, 'student_id' => 'S-' . uniqid(), 'course' => 'Nursing'],
            'faculty'   => ['campus_id' => $this->campus->id, 'department' => 'Nursing'],
            'lab_staff' => ['campus_id' => $this->campus->id],
            'admin'     => [],
        };
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active'] + $this->roleFields($role));
    }

    private function changeRole(User $user, string $role, ?array $fields = null)
    {
        return $this->actingAs($this->admin)->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
        ] + ($fields ?? $this->roleFields($role)));
    }

    /** Every parameterless GET page under a role's URL prefix. */
    private function pagesFor(string $role): array
    {
        $prefix = self::PREFIX[$role];

        return collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods()) && str_starts_with($r->uri(), "$prefix/") && !str_contains($r->uri(), '{'))
            ->map(fn ($r) => '/' . $r->uri())
            ->reject(fn ($uri) => in_array($uri, self::POSTGRES_ONLY_PAGES, true))
            ->values()
            ->all();
    }

    public static function roleChanges(): array
    {
        $roles = ['student', 'faculty', 'lab_staff', 'admin'];
        $cases = [];
        foreach ($roles as $from) {
            foreach ($roles as $to) {
                if ($from !== $to) {
                    $cases["$from to $to"] = [$from, $to];
                }
            }
        }
        return $cases;
    }

    #[DataProvider('roleChanges')]
    public function test_a_changed_account_can_use_every_page_of_its_new_role(string $from, string $to): void
    {
        $user = $this->makeUser($from);
        $usersBefore = User::count();

        $this->changeRole($user, $to)->assertSessionHasNoErrors()->assertRedirect('/admin/users');

        $user = $user->fresh();
        $this->assertSame($to, $user->role);
        $this->assertSame($usersBefore, User::count(), 'A role change must update the account in place, not create another.');

        // Logs in to the new role's dashboard…
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route(self::PREFIX[$to] . '.dashboard'));

        // …every page of the new role opens without a server error…
        $pages = $this->pagesFor($to);
        $this->assertNotEmpty($pages);
        foreach ($pages as $page) {
            $status = $this->actingAs($user)->get($page)->getStatusCode();
            $this->assertTrue($status < 400, "$from -> $to: GET $page returned $status");
        }

        // …and the old role's pages are refused by the backend.
        $this->actingAs($user)->get('/' . self::PREFIX[$from] . '/dashboard')->assertForbidden();
    }

    public function test_role_specific_fields_are_required_for_the_new_role(): void
    {
        $student = $this->makeUser('student');

        $this->changeRole($student, 'faculty', [])->assertSessionHasErrors(['campus_id', 'department']);
        $this->changeRole($student, 'lab_staff', [])->assertSessionHasErrors(['campus_id']);

        $faculty = $this->makeUser('faculty');
        $this->changeRole($faculty, 'student', [])->assertSessionHasErrors(['campus_id', 'student_id', 'course']);

        $this->assertSame('student', $student->fresh()->role);
        $this->assertSame('faculty', $faculty->fresh()->role);
    }

    public function test_an_admin_account_does_not_need_a_campus(): void
    {
        $this->actingAs($this->admin); // a second admin exists, so demotion guards don't apply
        $student = $this->makeUser('student');

        $this->changeRole($student, 'admin', [])->assertSessionHasNoErrors();
        $this->assertSame('admin', $student->fresh()->role);
    }

    public function test_a_student_id_already_used_by_another_account_is_rejected(): void
    {
        $existing = $this->makeUser('student');
        $faculty = $this->makeUser('faculty');

        $this->changeRole($faculty, 'student', ['campus_id' => $this->campus->id, 'student_id' => $existing->student_id, 'course' => 'Nursing'])
            ->assertSessionHasErrors(['student_id']);
        $this->assertSame('faculty', $faculty->fresh()->role);
    }

    public function test_leaving_the_student_role_clears_student_only_fields_but_keeps_records(): void
    {
        $student = $this->makeUser('student');
        $borrowing = Borrowing::create([
            'user_id' => $student->id, 'borrow_date' => now(), 'return_date' => now()->addDay(),
            'status' => 'returned', 'claim_code' => 'CHEM-REQ-TEST-' . uniqid(),
        ]);

        $this->changeRole($student, 'faculty')->assertSessionHasNoErrors();

        $student = $student->fresh();
        $this->assertNull($student->student_id);
        $this->assertNull($student->course);
        $this->assertSame('Nursing', $student->department);
        $this->assertTrue($student->hasCompletedProfile());
        $this->assertSame($student->id, $borrowing->fresh()->user_id);
    }

    public function test_a_faculty_member_with_requests_awaiting_their_approval_cannot_be_demoted(): void
    {
        $faculty = $this->makeUser('faculty');
        $student = $this->makeUser('student');
        Borrowing::create([
            'user_id' => $student->id, 'faculty_id' => $faculty->id, 'borrow_date' => now(), 'return_date' => now()->addDay(),
            'status' => 'pending_faculty', 'claim_code' => 'CHEM-REQ-TEST-' . uniqid(),
        ]);

        $this->changeRole($faculty, 'student')->assertSessionHasErrors(['role']);
        $this->assertSame('faculty', $faculty->fresh()->role);
    }

    public function test_the_role_change_is_recorded_in_the_audit_log(): void
    {
        $student = $this->makeUser('student');

        $this->changeRole($student, 'lab_staff')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id, 'action' => 'Changed User Role', 'record_id' => $student->id,
        ]);
    }

    public function test_a_signed_in_user_whose_role_changed_is_sent_to_their_new_dashboard(): void
    {
        $user = $this->makeUser('student');
        $this->changeRole($user, 'faculty')->assertSessionHasNoErrors();

        // A click from a stale student page (Inertia visit) lands on the
        // faculty dashboard with a message instead of an error modal.
        $this->actingAs($user->fresh())
            ->get('/student/borrowings', [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    public function test_a_student_cannot_route_a_request_to_a_non_faculty_account(): void
    {
        $student = $this->makeUser('student');
        $notFaculty = $this->makeUser('lab_staff');

        $this->actingAs($student)->post('/student/borrowings', [
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDay()->toDateString(),
            'purpose' => 'Lab work',
            'subject' => 'Chemistry',
            'faculty_id' => $notFaculty->id,
            'equipment' => [['equipment_id' => 1, 'inventory_id' => 1, 'quantity' => 1]],
        ])->assertSessionHasErrors(['faculty_id']);
    }

    public function test_the_admin_user_forms_offer_every_role_and_a_campus_list(): void
    {
        $labStaff = $this->makeUser('lab_staff');

        $this->actingAs($this->admin)->get("/admin/users/{$labStaff->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('user.role', 'lab_staff')->has('campuses', 1));
        $this->actingAs($this->admin)->get('/admin/users/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('campuses', 1));
    }
}
