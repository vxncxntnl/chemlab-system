<?php

namespace App\Services;

use App\Exceptions\InvalidBorrowingStateException;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancelling an activity used to be done by deleting it, which only
 * unlinked its borrow requests (activity_id ON DELETE SET NULL) and left
 * them waiting for approval with no activity behind them. Cancelling keeps
 * the activity's record and closes out its requests properly.
 */
class ActivityService
{
    public function __construct(private BorrowingService $borrowingService)
    {
    }

    public function hasEquipmentOut(Activity $activity): bool
    {
        return $activity->borrowings()->whereIn('status', ['borrowed', 'overdue'])->exists();
    }

    public function hasAnyRequests(Activity $activity): bool
    {
        return Borrowing::withTrashed()->where('activity_id', $activity->id)->exists();
    }

    /**
     * Marks the activity cancelled (its QR codes stop working) and cancels
     * every request not yet released, returning the stock and telling each
     * student why. Refused while any student still has equipment out.
     *
     * @return int number of requests cancelled
     */
    public function cancel(Activity $activity, User $actor): int
    {
        if ($this->hasEquipmentOut($activity)) {
            throw new InvalidBorrowingStateException(
                'Some students still have equipment from this activity. It must be returned before the activity can be cancelled.'
            );
        }

        $cancelled = DB::transaction(function () use ($activity, $actor) {
            $cancelled = [];
            foreach ($activity->borrowings()->whereIn('status', BorrowingService::UNRELEASED_STATUSES)->get() as $borrowing) {
                $cancelled[] = $this->borrowingService->cancelForActivity($borrowing, $actor->id, $activity->title);
            }

            $activity->update(['status' => 'cancelled']);
            AuditLog::log($actor->id, 'Cancelled Activity', 'activities', $activity->id, null, [
                'cancelled_requests' => count($cancelled),
            ]);

            return $cancelled;
        });

        // Notify only after the cancellation has committed.
        foreach ($cancelled as $borrowing) {
            NotificationService::borrowingCancelledByActivity($borrowing->fresh('user'), $activity, $actor);
        }

        return count($cancelled);
    }
}
