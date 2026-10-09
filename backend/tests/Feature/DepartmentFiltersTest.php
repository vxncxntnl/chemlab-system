<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Department (faculty) / course (student) on the Send Notification
 * recipients and the Audit Logs, and searches that ignore upper/lower case.
 */
class DepartmentFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private User $faculty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $carmen = Campus::create(['campus_name' => 'PHINMA COC – Carmen']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'campus_id' => $carmen->id]);
        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active', 'campus_id' => $carmen->id, 'student_id' => '02-1234', 'course' => 'Civil Engineering', 'name' => 'Vincent Quinto']);
        $this->faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active', 'campus_id' => $carmen->id, 'department' => 'Nursing', 'name' => 'Prof. Santos']);

        AuditLog::log($this->student->id, 'Created Borrow Request', 'borrowings', 1);
        AuditLog::log($this->faculty->id, 'Approved Borrow Request', 'borrowings', 1);
        AuditLog::log($this->admin->id, 'Deleted User', 'users', 99);
    }

    public function test_notification_recipients_include_department_or_course(): void
    {
        $this->actingAs($this->admin)->get('/admin/notifications/create')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('users', fn ($users) => collect($users)->firstWhere('id', $this->student->id)['department'] === 'Civil Engineering'
                    && collect($users)->firstWhere('id', $this->faculty->id)['department'] === 'Nursing'
                    && collect($users)->firstWhere('id', $this->student->id)['id_number'] === '02-1234'));
    }

    public function test_audit_log_action_search_ignores_upper_and_lower_case(): void
    {
        foreach (['created', 'CREATED', 'Created', 'cReAtEd'] as $term) {
            $this->actingAs($this->admin)->get('/admin/reports/audit-logs?action=' . $term)
                ->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('logs.data.0.action', 'Created Borrow Request'));
        }
    }

    public function test_audit_logs_show_and_filter_by_department(): void
    {
        $this->actingAs($this->admin)->get('/admin/reports/audit-logs')
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 3)
                ->where('departments', ['Civil Engineering', 'Nursing'])
                ->where('logs.data', fn ($logs) => collect($logs)->firstWhere('user_id', $this->student->id)['department'] === 'Civil Engineering'
                    && collect($logs)->firstWhere('user_id', $this->admin->id)['department'] === null));

        $this->actingAs($this->admin)->get('/admin/reports/audit-logs?department=Nursing')
            ->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('logs.data.0.action', 'Approved Borrow Request'));
    }
}
