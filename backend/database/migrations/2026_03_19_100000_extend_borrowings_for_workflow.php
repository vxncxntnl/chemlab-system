<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite cannot alter a table that is referenced by a view,
        // so drop the views that depend on borrowings before changing it.
        DB::statement('DROP VIEW IF EXISTS borrow_requests');
        DB::statement('DROP VIEW IF EXISTS transactions');

        Schema::table('borrowings', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('purpose');
            $table->foreignId('faculty_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();

            $table->foreignId('faculty_approved_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('faculty_approved_at')->nullable()->after('faculty_approved_by');

            $table->foreignId('lab_approved_by')->nullable()->after('faculty_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('lab_approved_at')->nullable()->after('lab_approved_by');

            $table->foreignId('admin_approved_by')->nullable()->after('lab_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('admin_approved_at')->nullable()->after('admin_approved_by');

            $table->string('claim_code')->nullable()->unique()->after('overdue_flag');
        });

        // Update status column allowed values
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

        // Re-create views that alias borrowings / borrowing_equipment
        DB::statement('CREATE VIEW IF NOT EXISTS borrow_requests AS SELECT * FROM borrowings');
        DB::statement('CREATE VIEW IF NOT EXISTS transactions AS SELECT * FROM borrowing_equipment');
    }

    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropUnique(['claim_code']);
            $table->dropColumn([
                'subject',
                'faculty_id',
                'faculty_approved_by',
                'faculty_approved_at',
                'lab_approved_by',
                'lab_approved_at',
                'admin_approved_by',
                'admin_approved_at',
                'claim_code',
            ]);

            $table->enum('status', [
                'pending',
                'approved',
                'borrowed',
                'returned',
                'overdue',
                'cancelled',
            ])->default('pending')->change();
        });
    }
};

