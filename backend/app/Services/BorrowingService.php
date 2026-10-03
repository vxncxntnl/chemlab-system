<?php

namespace App\Services;

use App\Exceptions\BorrowingLimitExceededException;
use App\Exceptions\DuplicateSubmissionException;
use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InvalidBorrowingStateException;
use App\Mail\BorrowingSubmitted;
use App\Models\Borrowing;
use App\Models\BorrowingEquipment;
use App\Models\DamageReport;
use App\Models\EquipmentInventory;
use App\Models\Equipment;
use App\Models\AuditLog;
use App\Models\Reservation;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BorrowingService
{
    /**
     * Statuses that count as "active" (not yet returned) toward a
     * borrower's 2-active-transaction limit — everything except a
     * finished (returned) or ended (cancelled/rejected) request.
     */
    private const ACTIVE_STATUSES = ['pending_faculty', 'pending_admin', 'approved', 'borrowed', 'overdue'];

    public const MAX_ACTIVE_BORROWINGS = 2;

    public function activeBorrowingCount(int $userId): int
    {
        return Borrowing::where('user_id', $userId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->count();
    }

    /**
     * SQLite (unlike MySQL/Postgres) has no real row-level locking — a
     * concurrent writer that loses the race gets an immediate "database is
     * locked" driver error instead of waiting and re-reading updated data.
     * `lockForUpdate()` plus a busy_timeout (config/database.php) already
     * makes it wait briefly rather than fail instantly, but under real
     * contention it can still surface this raw error. Since it only ever
     * happens at exactly the moment two requests genuinely collided, it's
     * safe to treat it the same as the clean, expected rejection.
     */
    private function isConcurrentWriteConflict(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'database is locked') || str_contains($message, 'deadlock');
    }

    /**
     * Reserves the "content fingerprint" of a borrowing submission (same
     * user, same equipment/quantities, same dates) for a short window,
     * using Laravel's atomic Cache::lock() — backed by the database cache
     * driver here, which enforces the exclusivity with a real unique-insert
     * under the hood, not a check-then-set race. A double-click fires two
     * requests with byte-for-byte identical form data; the second one to
     * reach this call finds the lock already held and is rejected outright,
     * before it ever reaches inventory reservation or record creation. The
     * lock is deliberately NOT released on success — it's left to expire via
     * TTL — so a near-simultaneous second request can't slip in between the
     * first request finishing and the lock being released.
     */
    private function acquireSubmissionLock(int $userId, array $data, array $equipment): Lock
    {
        $fingerprint = md5(json_encode([
            'user' => $userId,
            'borrow_date' => $data['borrow_date'] ?? null,
            'return_date' => $data['return_date'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'equipment' => collect($equipment)
                ->map(fn ($item) => "{$item['equipment_id']}:{$item['inventory_id']}:{$item['quantity']}")
                ->sort()
                ->values()
                ->all(),
        ]));

        $lock = Cache::lock("borrowing-submit:{$fingerprint}", 10);

        if (!$lock->get()) {
            throw new DuplicateSubmissionException(
                'This request was already submitted a moment ago. Please wait a few seconds before trying again.'
            );
        }

        return $lock;
    }

    /**
     * Locks the borrowing row and re-verifies it is still in one of
     * $allowedStatuses before the caller applies a state transition — the
     * same pattern doReturnBorrowing() already used. Without this, two
     * near-simultaneous requests (a double-click, or two staff processing
     * the same request) can both pass a pre-lock status check in the
     * controller and both apply the transition: duplicating audit log
     * entries/emails, or — for a reject/cancel that restores inventory —
     * crediting stock back twice.
     *
     * @throws InvalidBorrowingStateException if the row is no longer in $allowedStatuses
     */
    private function lockBorrowingInStatus(Borrowing $borrowing, array $allowedStatuses): Borrowing
    {
        $locked = Borrowing::where('id', $borrowing->id)->lockForUpdate()->first();

        if (!$locked || !in_array($locked->status, $allowedStatuses, true)) {
            throw new InvalidBorrowingStateException('This request has already been processed.');
        }

        return $locked;
    }

    /**
     * Cryptographically random claim code — replaces an earlier version
     * derived from the borrowing's own sequential public ID (e.g.
     * CHEM-REQ-2026-00042), which meant anyone could guess a valid code for
     * a nearby borrowing just by incrementing the number. Str::random() uses
     * a CSPRNG; 10 characters from a 62-character alphabet is ~59 bits of
     * entropy, and the uniqueness loop is a belt-and-suspenders check since
     * a collision is astronomically unlikely at that size.
     */
    private function generateClaimCode(): string
    {
        do {
            $code = 'CHEM-' . now()->format('Y') . '-' . Str::upper(Str::random(10));
        } while (Borrowing::where('claim_code', $code)->exists());

        return $code;
    }

    /**
     * Lock and re-verify every requested inventory row, then decrement it —
     * all inside the caller's transaction. This is the one place that
     * actually guards against overselling: the earlier "is this available?"
     * check controllers do before calling in is only a UX nicety (fails
     * fast, avoids building a form response for something clearly gone) and
     * is NOT the security boundary — two requests can pass that check at the
     * same instant. `lockForUpdate()` forces a second concurrent transaction
     * to wait for the first to finish before it can even read the row, so by
     * the time it re-checks quantity here, it sees the already-decremented
     * value and correctly fails instead of overselling.
     *
     * @throws InsufficientInventoryException if any item no longer has enough stock,
     *         or is held by someone else's approved reservation for the requested dates.
     */
    private function reserveInventory(array $equipment, ?string $borrowDate = null, ?string $returnDate = null, ?int $userId = null): void
    {
        foreach ($equipment as $item) {
            $inventory = EquipmentInventory::where('id', $item['inventory_id'])
                ->lockForUpdate()
                ->first();

            if (!$inventory || $inventory->equipment_id !== $item['equipment_id']) {
                throw new InsufficientInventoryException('One or more selected items could not be found.');
            }

            if ($inventory->available_quantity < $item['quantity']) {
                throw new InsufficientInventoryException(
                    "Only {$inventory->available_quantity} unit(s) of this item are available now — someone else may have just borrowed the rest."
                );
            }

            // An approved reservation is meant to actually hold the equipment
            // for its date range — previously nothing checked this, so an
            // "approved" reservation was purely cosmetic and a second person
            // could freely borrow the same item for the same dates anyway.
            if ($borrowDate && $returnDate && $this->hasConflictingApprovedReservation(
                $item['equipment_id'], $borrowDate, $returnDate, $userId
            )) {
                throw new InsufficientInventoryException(
                    'This equipment is reserved by someone else for the requested dates. Please choose different dates or contact the administrator.'
                );
            }

            $inventory->decrement('available_quantity', $item['quantity']);
        }
    }

    /**
     * Standard date-range overlap check: two ranges intersect when each one
     * starts before (or when) the other ends. A user borrowing equipment
     * they reserved for themselves isn't a conflict — excluded via user_id.
     */
    private function hasConflictingApprovedReservation(int $equipmentId, string $borrowDate, string $returnDate, ?int $userId): bool
    {
        return Reservation::where('equipment_id', $equipmentId)
            ->where('status', 'approved')
            ->when($userId, fn ($q) => $q->where('user_id', '!=', $userId))
            ->where('reserved_date', '<=', $returnDate)
            ->where('reserved_until', '>=', $borrowDate)
            ->exists();
    }

    public function createBorrowing(array $data, array $equipment, int $userId, bool $notifyFaculty = true): Borrowing
    {
        if ($this->activeBorrowingCount($userId) >= self::MAX_ACTIVE_BORROWINGS) {
            throw new BorrowingLimitExceededException(
                'You already have ' . self::MAX_ACTIVE_BORROWINGS . ' active borrowing requests. Please wait for one to be returned before submitting a new request.'
            );
        }

        $lock = $this->acquireSubmissionLock($userId, $data, $equipment);
        try {
            return $this->doCreateBorrowing($data, $equipment, $userId, $notifyFaculty);
        } catch (QueryException $e) {
            $lock->release();
            if ($this->isConcurrentWriteConflict($e)) {
                throw new InsufficientInventoryException('This item is being requested by someone else right now — please try again in a moment.');
            }
            throw $e;
        } catch (InsufficientInventoryException $e) {
            // Nothing was created — no reason to keep blocking this exact
            // resubmission for the full lock TTL.
            $lock->release();
            throw $e;
        }
    }

    private function doCreateBorrowing(array $data, array $equipment, int $userId, bool $notifyFaculty): Borrowing
    {
        return DB::transaction(function () use ($data, $equipment, $userId, $notifyFaculty) {
            // Reserve stock FIRST, before creating any records — if this
            // throws, the transaction rolls back and nothing is left behind.
            $this->reserveInventory($equipment, $data['borrow_date'] ?? null, $data['return_date'] ?? null, $userId);

            $borrowing = Borrowing::create([
                'user_id' => $userId,
                'faculty_id' => $data['faculty_id'] ?? null,
                'activity_id' => $data['activity_id'] ?? null,
                'borrow_date' => $data['borrow_date'],
                'return_date' => $data['return_date'],
                'purpose' => $data['purpose'] ?? null,
                'subject' => $data['subject'] ?? null,
                'status' => 'pending_faculty',
            ]);

            // Generate claim code for secure verification
            $borrowing->claim_code = $this->generateClaimCode();
            $borrowing->save();

            foreach ($equipment as $item) {
                BorrowingEquipment::create([
                    'borrowing_id' => $borrowing->id,
                    'equipment_id' => $item['equipment_id'],
                    'inventory_id' => $item['inventory_id'],
                    'quantity' => $item['quantity'],
                    'condition_on_borrow' => $item['condition'] ?? 'good',
                ]);
            }

            AuditLog::log($userId, 'Created Borrow Request', 'borrowings', $borrowing->id);

            // Notify the student that their request was submitted
            $borrowing->load('user', 'faculty');
            NotificationService::borrowingSubmitted($borrowing, $notifyFaculty);

            return $borrowing;
        });
    }

    /**
     * Lab-staff-initiated borrow: skips faculty review entirely (lab staff
     * can't approve their own requests) and goes straight to pending_admin.
     * Kept as its own method — rather than overloading createBorrowing()
     * with a growing list of status/notification flags — but shares the
     * exact same locked reserveInventory() used everywhere else, which is
     * the part that actually has to be identical everywhere.
     */
    public function createLabInitiatedBorrowing(array $data, array $equipment, int $labStaffId): Borrowing
    {
        $lock = $this->acquireSubmissionLock($labStaffId, $data, $equipment);
        try {
            return $this->doCreateLabInitiatedBorrowing($data, $equipment, $labStaffId);
        } catch (QueryException $e) {
            $lock->release();
            if ($this->isConcurrentWriteConflict($e)) {
                throw new InsufficientInventoryException('This item is being requested by someone else right now — please try again in a moment.');
            }
            throw $e;
        } catch (InsufficientInventoryException $e) {
            $lock->release();
            throw $e;
        }
    }

    private function doCreateLabInitiatedBorrowing(array $data, array $equipment, int $labStaffId): Borrowing
    {
        return DB::transaction(function () use ($data, $equipment, $labStaffId) {
            $this->reserveInventory($equipment, $data['borrow_date'] ?? null, $data['return_date'] ?? null, $labStaffId);

            $borrowing = Borrowing::create([
                'user_id' => $labStaffId,
                'borrow_date' => $data['borrow_date'],
                'return_date' => $data['return_date'],
                'purpose' => $data['purpose'] ?? null,
                'status' => 'pending_admin',
                'lab_approved_by' => $labStaffId,
                'lab_approved_at' => now(),
            ]);

            $borrowing->claim_code = $this->generateClaimCode();
            $borrowing->save();

            foreach ($equipment as $item) {
                BorrowingEquipment::create([
                    'borrowing_id' => $borrowing->id,
                    'equipment_id' => $item['equipment_id'],
                    'inventory_id' => $item['inventory_id'],
                    'quantity' => $item['quantity'],
                    'condition_on_borrow' => 'good',
                ]);
            }

            AuditLog::log($labStaffId, 'Created Borrow Request (Lab Staff)', 'borrowings', $borrowing->id);

            return $borrowing;
        });
    }

    public function approveBorrowing(Borrowing $borrowing, int $approverId): Borrowing
    {
        // Faculty approval is optional — admin can approve directly from
        // 'pending_faculty' too, not only after faculty has already moved it
        // to 'pending_admin'. If skipped this way, faculty_approved_by/_at
        // simply stay null, which correctly reflects that faculty never
        // acted on it.
        $locked = $this->transitionLocked($borrowing, ['pending_admin', 'pending_faculty'], function (Borrowing $locked) use ($approverId) {
            $locked->update([
                'status' => 'approved',
                'approved_by' => $approverId,
                'admin_approved_by' => $approverId,
                'admin_approved_at' => now(),
            ]);
            AuditLog::log($approverId, 'Approved Borrow Request (Final)', 'borrowings', $locked->id);
        });

        // Email is sent by the caller via NotificationService::borrowingApprovedByAdmin(),
        // which names the approving admin — sending a second one here duplicated it.
        return $locked;
    }

    public function rejectBorrowing(Borrowing $borrowing, int $userId, ?string $reason = null): Borrowing
    {
        // Same as approveBorrowing() — admin can reject directly from
        // 'pending_faculty' too, for consistency with the optional-faculty
        // approval workflow.
        $locked = $this->transitionLocked($borrowing, ['pending_admin', 'pending_faculty'], function (Borrowing $locked) use ($userId, $reason) {
            // Restore reserved quantity — inside the same locked check as the
            // status change, so a second concurrent reject attempt sees the
            // already-cancelled status and is rejected before it can credit
            // inventory back a second time.
            foreach ($locked->borrowingEquipment as $item) {
                $inventory = EquipmentInventory::find($item->inventory_id);
                $inventory?->increment('available_quantity', $item->quantity);
            }
            $locked->update(['status' => 'cancelled', 'rejection_reason' => $reason]);
            AuditLog::log($userId, 'Rejected Borrow Request', 'borrowings', $locked->id, null, $reason ? ['reason' => $reason] : null);
        });

        // Email is sent by the caller via NotificationService::borrowingRejectedByAdmin(),
        // which names the rejecting admin and includes the reason — sending a
        // second one here duplicated it.
        return $locked;
    }

    public function markAsBorrowed(Borrowing $borrowing, int $userId): Borrowing
    {
        return $this->transitionLocked($borrowing, ['approved'], function (Borrowing $locked) use ($userId) {
            $locked->update(['status' => 'borrowed']);
            AuditLog::log($userId, 'Equipment Released to Borrower', 'borrowings', $locked->id);
        });
    }

    /**
     * Faculty's own approval stage (pending_faculty -> pending_admin). Kept
     * separate from approveBorrowing() (which handles the later admin stage)
     * since the two check a different current status and a different actor
     * relationship — mirrors the existing createBorrowing vs
     * createLabInitiatedBorrowing split rather than one method with growing
     * status/role flags.
     */
    public function facultyApproveBorrowing(Borrowing $borrowing, int $facultyId): Borrowing
    {
        return $this->transitionLocked($borrowing, ['pending_faculty'], function (Borrowing $locked) use ($facultyId) {
            if ($locked->faculty_id !== $facultyId) {
                throw new InvalidBorrowingStateException('You are not the assigned faculty for this request.');
            }
            $locked->update([
                'status' => 'pending_admin',
                'faculty_approved_by' => $facultyId,
                'faculty_approved_at' => now(),
            ]);
            AuditLog::log($facultyId, 'Approved Borrow Request (Faculty)', 'borrowings', $locked->id);
        });
    }

    public function facultyRejectBorrowing(Borrowing $borrowing, int $facultyId, ?string $reason = null): Borrowing
    {
        return $this->transitionLocked($borrowing, ['pending_faculty'], function (Borrowing $locked) use ($facultyId, $reason) {
            if ($locked->faculty_id !== $facultyId) {
                throw new InvalidBorrowingStateException('You are not the assigned faculty for this request.');
            }
            foreach ($locked->borrowingEquipment as $item) {
                $inventory = EquipmentInventory::find($item->inventory_id);
                $inventory?->increment('available_quantity', $item->quantity);
            }
            $locked->update(['status' => 'cancelled', 'rejection_reason' => $reason]);
            AuditLog::log($facultyId, 'Rejected Borrow Request (Faculty)', 'borrowings', $locked->id, null, $reason ? ['reason' => $reason] : null);
        });
    }

    /**
     * Self-service cancellation (student or the assigned faculty, while
     * still pending_faculty). Shares the same lock-and-recheck pattern for
     * the same reason as reject: a double-click here would otherwise credit
     * the reserved inventory back twice.
     */
    public function cancelBorrowing(Borrowing $borrowing, int $userId, string $auditLabel): Borrowing
    {
        return $this->transitionLocked($borrowing, ['pending_faculty'], function (Borrowing $locked) use ($userId, $auditLabel) {
            foreach ($locked->borrowingEquipment as $item) {
                $inventory = EquipmentInventory::find($item->inventory_id);
                $inventory?->increment('available_quantity', $item->quantity);
            }
            $locked->update(['status' => 'cancelled']);
            AuditLog::log($userId, $auditLabel, 'borrowings', $locked->id);
        });
    }

    /**
     * Shared plumbing for every status transition above: open a
     * transaction, lock the row, re-verify it's still in an allowed status,
     * run the caller's update logic, and translate a raw SQLite lock
     * conflict into the same clean exception a normal lost race produces.
     */
    private function transitionLocked(Borrowing $borrowing, array $allowedStatuses, callable $apply): Borrowing
    {
        try {
            return DB::transaction(function () use ($borrowing, $allowedStatuses, $apply) {
                $locked = $this->lockBorrowingInStatus($borrowing, $allowedStatuses);
                $apply($locked);
                return $locked;
            });
        } catch (QueryException $e) {
            if ($this->isConcurrentWriteConflict($e)) {
                throw new InvalidBorrowingStateException('This request is being processed by another request right now — please refresh and try again.');
            }
            throw $e;
        }
    }

    public function returnBorrowing(Borrowing $borrowing, array $conditions, int $userId, ?string $liableParty = null, ?string $liableReason = null): Borrowing
    {
        try {
            return $this->doReturnBorrowing($borrowing, $conditions, $userId, $liableParty, $liableReason);
        } catch (QueryException $e) {
            if ($this->isConcurrentWriteConflict($e)) {
                throw new InvalidBorrowingStateException('This borrowing is being updated by another request right now — please refresh and try again.');
            }
            throw $e;
        }
    }

    private function doReturnBorrowing(Borrowing $borrowing, array $conditions, int $userId, ?string $liableParty, ?string $liableReason): Borrowing
    {
        return DB::transaction(function () use ($borrowing, $conditions, $userId, $liableParty, $liableReason) {
            // Re-read the row under a lock and re-check its status — a second
            // "return" request (double-click, or two staff processing the
            // same drop-off) that raced past the controller's earlier check
            // must not also succeed once it actually gets the lock.
            $locked = Borrowing::where('id', $borrowing->id)->lockForUpdate()->first();
            if (!$locked || !in_array($locked->status, ['borrowed', 'overdue'], true)) {
                throw new InvalidBorrowingStateException('This borrowing has already been returned or is not in a returnable state.');
            }

            foreach ($borrowing->borrowingEquipment as $item) {
                $itemCondition = $conditions[$item->id] ?? [];
                $goodQty    = max(0, (int) ($itemCondition['good']    ?? $item->quantity));
                $damagedQty = max(0, (int) ($itemCondition['damaged'] ?? 0));

                // Ensure totals don't exceed borrowed quantity
                if ($goodQty + $damagedQty > $item->quantity) {
                    $goodQty = $item->quantity - $damagedQty;
                }

                $inventory = EquipmentInventory::find($item->inventory_id);

                // Restore only the good quantity back to available stock
                if ($goodQty > 0) {
                    $inventory?->increment('available_quantity', $goodQty);
                }

                // Handle damaged items
                if ($damagedQty > 0) {
                    $item->update(['condition_on_return' => 'damaged']);

                    $report = DamageReport::create([
                        'borrowing_id'           => $borrowing->id,
                        'equipment_id'           => $item->equipment_id,
                        'quantity'               => $damagedQty,
                        'equipment_inventory_id' => $item->inventory_id,
                        'reported_by'            => $userId,
                        'description'            => "{$damagedQty} out of {$item->quantity} unit(s) returned damaged by lab staff.",
                        'liable_party'           => $liableParty,
                        'liable_reason'          => $liableReason,
                        'status'                 => 'pending',
                    ]);

                    $report->load('borrowing.user', 'borrowing.faculty', 'equipment');
                    NotificationService::damageReportFiled($report);
                } else {
                    $item->update(['condition_on_return' => 'good']);
                }
            }

            $borrowing->update([
                'status'             => 'returned',
                'actual_return_date' => now(),
            ]);

            AuditLog::log($userId, 'Equipment Returned', 'borrowings', $borrowing->id);

            return $borrowing;
        });
    }
}
