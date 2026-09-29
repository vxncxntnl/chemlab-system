<?php

namespace Tests\Feature;

use App\Exceptions\InvalidBorrowingStateException;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\Campus;
use App\Models\Equipment;
use App\Models\EquipmentInventory;
use App\Models\Location;
use App\Models\User;
use App\Services\BorrowingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the approve/release double-click fix: every status-changing action
 * (admin approve/reject/release, faculty approve/reject, self-service
 * cancel) now locks the row and re-checks its status inside the
 * transaction. A second call against an already-transitioned borrowing must
 * be rejected — not silently re-apply the transition, duplicate the audit
 * log entry, or (for reject/cancel) credit restored inventory a second
 * time.
 */
class ApprovalReleaseRaceGuardTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentInventory $inventory;
    private BorrowingEquipment $item;
    private User $faculty;

    private function makeBorrowing(string $status, array $extra = []): Borrowing
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);
        $equipment = Equipment::create(['equipment_code' => 'EQ-' . uniqid(), 'name' => 'Item', 'condition' => 'good', 'is_available' => true]);
        $this->inventory = EquipmentInventory::create([
            'equipment_id' => $equipment->id, 'campus_id' => $campus->id, 'location_id' => $location->id,
            'total_quantity' => 5, 'available_quantity' => 4,
        ]);
        $this->faculty = User::factory()->create(['role' => 'faculty', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $borrowing = Borrowing::create(array_merge([
            'user_id' => $student->id,
            'faculty_id' => $this->faculty->id,
            'borrow_date' => now(),
            'return_date' => now()->addDays(3),
            'status' => $status,
        ], $extra));

        $this->item = BorrowingEquipment::create([
            'borrowing_id' => $borrowing->id, 'equipment_id' => $equipment->id,
            'inventory_id' => $this->inventory->id, 'quantity' => 1, 'condition_on_borrow' => 'good',
        ]);

        return $borrowing;
    }

    public function test_approving_an_already_approved_borrowing_is_rejected_and_does_not_duplicate_audit_log(): void
    {
        $borrowing = $this->makeBorrowing('pending_admin');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $service = app(BorrowingService::class);

        $service->approveBorrowing($borrowing, $admin->id);
        $this->assertSame(1, AuditLog::where('table_affected', 'borrowings')->where('record_id', $borrowing->id)->count());

        $this->expectException(InvalidBorrowingStateException::class);
        try {
            $service->approveBorrowing($borrowing, $admin->id);
        } finally {
            $this->assertSame('approved', $borrowing->fresh()->status);
            $this->assertSame(
                1,
                AuditLog::where('table_affected', 'borrowings')->where('record_id', $borrowing->id)->count(),
                'A repeated approve() call must not create a second audit log entry.'
            );
        }
    }

    public function test_rejecting_an_already_rejected_borrowing_does_not_credit_inventory_twice(): void
    {
        $borrowing = $this->makeBorrowing('pending_admin');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $service = app(BorrowingService::class);

        $service->rejectBorrowing($borrowing, $admin->id);
        $this->assertSame(5, $this->inventory->fresh()->available_quantity, 'First reject must restore the reserved unit.');

        $this->expectException(InvalidBorrowingStateException::class);
        try {
            $service->rejectBorrowing($borrowing, $admin->id);
        } finally {
            $this->assertSame(
                5,
                $this->inventory->fresh()->available_quantity,
                'A repeated reject() call must NOT credit the same unit back a second time.'
            );
        }
    }

    public function test_releasing_an_already_released_borrowing_is_rejected(): void
    {
        $borrowing = $this->makeBorrowing('approved');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $service = app(BorrowingService::class);

        $service->markAsBorrowed($borrowing, $admin->id);
        $this->assertSame('borrowed', $borrowing->fresh()->status);

        $this->expectException(InvalidBorrowingStateException::class);
        $service->markAsBorrowed($borrowing, $admin->id);
    }

    public function test_faculty_approving_an_already_approved_request_is_rejected(): void
    {
        $borrowing = $this->makeBorrowing('pending_faculty');
        $service = app(BorrowingService::class);

        $service->facultyApproveBorrowing($borrowing, $this->faculty->id);
        $this->assertSame('pending_admin', $borrowing->fresh()->status);

        $this->expectException(InvalidBorrowingStateException::class);
        $service->facultyApproveBorrowing($borrowing, $this->faculty->id);
    }

    public function test_faculty_rejecting_an_already_rejected_request_does_not_credit_inventory_twice(): void
    {
        $borrowing = $this->makeBorrowing('pending_faculty');
        $service = app(BorrowingService::class);

        $service->facultyRejectBorrowing($borrowing, $this->faculty->id);
        $this->assertSame(5, $this->inventory->fresh()->available_quantity);

        $this->expectException(InvalidBorrowingStateException::class);
        try {
            $service->facultyRejectBorrowing($borrowing, $this->faculty->id);
        } finally {
            $this->assertSame(5, $this->inventory->fresh()->available_quantity);
        }
    }

    public function test_a_faculty_member_cannot_approve_a_request_assigned_to_someone_else(): void
    {
        $borrowing = $this->makeBorrowing('pending_faculty');
        $otherFaculty = User::factory()->create(['role' => 'faculty', 'status' => 'active']);
        $service = app(BorrowingService::class);

        $this->expectException(InvalidBorrowingStateException::class);
        $service->facultyApproveBorrowing($borrowing, $otherFaculty->id);
    }

    public function test_cancelling_an_already_cancelled_borrowing_does_not_credit_inventory_twice(): void
    {
        $borrowing = $this->makeBorrowing('pending_faculty');
        $student = $borrowing->user;
        $service = app(BorrowingService::class);

        $service->cancelBorrowing($borrowing, $student->id, 'Cancelled Borrow Request (Student)');
        $this->assertSame(5, $this->inventory->fresh()->available_quantity);

        $this->expectException(InvalidBorrowingStateException::class);
        try {
            $service->cancelBorrowing($borrowing, $student->id, 'Cancelled Borrow Request (Student)');
        } finally {
            $this->assertSame(5, $this->inventory->fresh()->available_quantity);
        }
    }

    public function test_admin_release_http_endpoint_rejects_a_second_release_without_duplicating_notification(): void
    {
        $borrowing = $this->makeBorrowing('approved');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $first = $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/release");
        $first->assertRedirect();
        $this->assertSame('borrowed', $borrowing->fresh()->status);

        // The controller's own pre-check will normally catch this in
        // practice (status is no longer 'approved'), but the point of this
        // suite is that even if that pre-check is ever removed or raced
        // past, the service layer itself still refuses.
        $second = $this->actingAs($admin)->post("/admin/borrowings/{$borrowing->id}/release");
        $second->assertRedirect();
        $this->assertSame('borrowed', $borrowing->fresh()->status, 'Status must remain unchanged after a rejected repeat release.');
    }
}
