<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Move existing pending_lab requests to pending_admin.
        DB::table('borrowings')
            ->where('status', 'pending_lab')
            ->update(['status' => 'pending_admin']);

        // Drop views before changing borrowings.
        DB::statement('DROP VIEW IF EXISTS borrow_requests');
        DB::statement('DROP VIEW IF EXISTS transactions');

        if (DB::getDriverName() === 'pgsql') {
            // Remove the old status constraint.
            DB::statement(
                'ALTER TABLE borrowings DROP CONSTRAINT IF EXISTS borrowings_status_check'
            );

            // Keep status as varchar instead of PostgreSQL enum.
            DB::statement("
                ALTER TABLE borrowings
                ALTER COLUMN status TYPE varchar(255)
                USING status::text
            ");

            DB::statement("
                ALTER TABLE borrowings
                ALTER COLUMN status SET DEFAULT 'pending_faculty'
            ");

            // Lab approval stage is no longer allowed.
            DB::statement("
                ALTER TABLE borrowings
                ADD CONSTRAINT borrowings_status_check
                CHECK (status IN (
                    'pending_faculty',
                    'pending_admin',
                    'approved',
                    'borrowed',
                    'returned',
                    'overdue',
                    'cancelled'
                ))
            ");
        } else {
            Schema::table('borrowings', function (Blueprint $table) {
                $table->enum('status', [
                    'pending_faculty',
                    'pending_admin',
                    'approved',
                    'borrowed',
                    'returned',
                    'overdue',
                    'cancelled',
                ])->default('pending_faculty')->change();
            });
        }

        // Re-create views.
        DB::statement('CREATE VIEW borrow_requests AS SELECT * FROM borrowings');
        DB::statement('CREATE VIEW transactions AS SELECT * FROM borrowing_equipment');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS borrow_requests');
        DB::statement('DROP VIEW IF EXISTS transactions');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE borrowings DROP CONSTRAINT IF EXISTS borrowings_status_check'
            );

            DB::statement("
                ALTER TABLE borrowings
                ALTER COLUMN status TYPE varchar(255)
                USING status::text
            ");

            DB::statement("
                ALTER TABLE borrowings
                ALTER COLUMN status SET DEFAULT 'pending_faculty'
            ");

            DB::statement("
                ALTER TABLE borrowings
                ADD CONSTRAINT borrowings_status_check
                CHECK (status IN (
                    'pending_faculty',
                    'pending_lab',
                    'pending_admin',
                    'approved',
                    'borrowed',
                    'returned',
                    'overdue',
                    'cancelled'
                ))
            ");
        } else {
            Schema::table('borrowings', function (Blueprint $table) {
                $table->enum('status', [
                    'pending_faculty',
                    'pending_lab',
                    'pending_admin',
                    'approved',
                    'borrowed',
                    'returned',
                    'overdue',
                    'cancelled',
                ])->default('pending_faculty')->change();
            });
        }

        DB::statement('CREATE VIEW borrow_requests AS SELECT * FROM borrowings');
        DB::statement('CREATE VIEW transactions AS SELECT * FROM borrowing_equipment');
    }
};