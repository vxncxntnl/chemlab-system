<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DamageReport;
use App\Models\EquipmentInventory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rejecting a damage report (no damage / filed by mistake) and reopening a
 * rejected one. Shared by the admin and lab staff screens so both follow the
 * same rules:
 * - Admin or lab staff can reject a Pending or Assessed report, with a reason.
 * - If the borrowing was already returned, the units held out of stock as
 *   damaged go back to available stock (same as Resolve does).
 * - A lab staff rejection notifies the admins, who can reopen it within
 *   DamageReport::REOPEN_DAYS days; reopening takes those units back out.
 */
class DamageReportService
{
    public function reject(DamageReport $report, string $reason, User $by): DamageReport
    {
        $report = DB::transaction(function () use ($report, $reason, $by) {
            $locked = DamageReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            if (!$locked->canBeRejected()) {
                throw ValidationException::withMessages(['reason' => 'Only a pending or assessed damage report can be rejected.']);
            }

            // Units are only held out of stock once the borrowing is
            // returned; while still borrowed there's nothing to put back.
            $restocked = 0;
            if ($locked->quantity && $locked->equipment_inventory_id && $locked->borrowing?->status === 'returned') {
                $inventory = EquipmentInventory::whereKey($locked->equipment_inventory_id)->lockForUpdate()->first();
                if ($inventory) {
                    $newAvailable = min($inventory->total_quantity, $inventory->available_quantity + $locked->quantity);
                    $restocked = $newAvailable - $inventory->available_quantity;
                    $inventory->update(['available_quantity' => $newAvailable]);
                }
            }

            $previous = $locked->status;
            $locked->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'rejected_by' => $by->id,
                'rejected_at' => now(),
                'status_before_rejection' => $previous,
                'rejection_restocked_qty' => $restocked,
            ]);

            AuditLog::log($by->id, 'Rejected Damage Report' . ($by->role === 'lab_staff' ? ' (Lab Staff)' : ''), 'damage_reports', $locked->id,
                ['status' => $previous],
                ['status' => 'rejected', 'reason' => $reason, 'restocked_quantity' => $restocked]
            );

            return $locked;
        });

        if ($by->role === 'lab_staff') {
            $report->load('equipment');
            NotificationService::damageReportRejectedByLabStaff($report, $by);
        }

        return $report;
    }

    public function reopen(DamageReport $report, User $by): DamageReport
    {
        return DB::transaction(function () use ($report, $by) {
            $locked = DamageReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            if (!$locked->canBeReopened()) {
                throw ValidationException::withMessages(['reopen' => 'A rejected report can only be reopened within ' . DamageReport::REOPEN_DAYS . ' days of being rejected.']);
            }

            // A new report may have been filed for the same item since (e.g.
            // at return) — reopening this one would count the damage twice.
            $newer = DamageReport::where('borrowing_id', $locked->borrowing_id)
                ->where('equipment_inventory_id', $locked->equipment_inventory_id)
                ->whereKeyNot($locked->id)
                ->where('status', '!=', 'rejected')
                ->exists();
            if ($newer) {
                throw ValidationException::withMessages(['reopen' => 'A newer damage report already exists for this item — update that one instead.']);
            }

            if ($locked->rejection_restocked_qty > 0) {
                $inventory = EquipmentInventory::whereKey($locked->equipment_inventory_id)->lockForUpdate()->first();
                if ($inventory) {
                    if ($inventory->available_quantity < $locked->rejection_restocked_qty) {
                        throw ValidationException::withMessages(['reopen' => "Can't reopen: the {$locked->rejection_restocked_qty} unit(s) put back in stock are already lent out again."]);
                    }
                    $inventory->decrement('available_quantity', $locked->rejection_restocked_qty);
                }
            }

            $restoredStatus = $locked->status_before_rejection ?: 'pending';
            AuditLog::log($by->id, 'Reopened Damage Report', 'damage_reports', $locked->id,
                ['status' => 'rejected', 'reason' => $locked->rejection_reason],
                ['status' => $restoredStatus]
            );

            $locked->update([
                'status' => $restoredStatus,
                'rejection_reason' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'status_before_rejection' => null,
                'rejection_restocked_qty' => 0,
            ]);

            return $locked;
        });
    }

    /**
     * Who did what to this report, oldest first — shown on the report page
     * so every decision has a name and time on it.
     */
    public function history(DamageReport $report): Collection
    {
        return AuditLog::with('user:id,name,role')
            ->where('table_affected', 'damage_reports')
            ->where('record_id', $report->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'user_id', 'action', 'new_values', 'created_at'])
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'reason' => $log->new_values['reason'] ?? null,
                'user' => $log->user?->name,
                'role' => $log->user?->role,
                'created_at' => $log->created_at,
            ]);
    }
}
