<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Damage reports can be rejected (no damage / filed by mistake) by admin or
 * lab staff, and an admin can reopen a rejected report within 7 days.
 * Adds the 'rejected' status and the fields that record who rejected it,
 * why, and what to undo on reopen. Existing rows are not changed.
 */
return new class extends Migration
{
    private const OLD_STATUSES = ['pending', 'assessed', 'resolved', 'waived'];
    private const NEW_STATUSES = ['pending', 'assessed', 'resolved', 'waived', 'rejected'];

    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('resolved_date');
            $table->foreignId('rejected_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            // What a reopen restores: the status before rejecting, and how
            // many units the rejection put back into available stock.
            $table->string('status_before_rejection', 20)->nullable()->after('rejected_at');
            $table->unsignedInteger('rejection_restocked_qty')->default(0)->after('status_before_rejection');
        });

        $this->setStatuses(self::NEW_STATUSES);
    }

    public function down(): void
    {
        DB::table('damage_reports')->where('status', 'rejected')
            ->update(['status' => DB::raw("COALESCE(status_before_rejection, 'pending')")]);

        $this->setStatuses(self::OLD_STATUSES);

        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejection_reason', 'rejected_at', 'status_before_rejection', 'rejection_restocked_qty']);
        });
    }

    private function setStatuses(array $statuses): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn ($s) => "'{$s}'", $statuses));
            DB::statement('ALTER TABLE damage_reports DROP CONSTRAINT IF EXISTS damage_reports_status_check');
            DB::statement("ALTER TABLE damage_reports ADD CONSTRAINT damage_reports_status_check CHECK (status IN ({$list}))");
        } else {
            Schema::table('damage_reports', function (Blueprint $table) use ($statuses) {
                $table->enum('status', $statuses)->default('pending')->change();
            });
        }
    }
};
