<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Lab Staff no longer approves/rejects requests — that decision belongs
        // to Admin alone. Any requests still sitting at pending_lab move on to
        // pending_admin so they aren't stranded in a status that no longer exists.
        DB::table('borrowings')->where('status', 'pending_lab')->update(['status' => 'pending_admin']);

        // SQLite cannot alter a table that is referenced by a view,
        // so drop the views that depend on borrowings before changing it.
        DB::statement('DROP VIEW IF EXISTS borrow_requests');
        DB::statement('DROP VIEW IF EXISTS transactions');

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

        DB::statement('CREATE VIEW IF NOT EXISTS borrow_requests AS SELECT * FROM borrowings');
        DB::statement('CREATE VIEW IF NOT EXISTS transactions AS SELECT * FROM borrowing_equipment');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS borrow_requests');
        DB::statement('DROP VIEW IF EXISTS transactions');

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

        DB::statement('CREATE VIEW IF NOT EXISTS borrow_requests AS SELECT * FROM borrowings');
        DB::statement('CREATE VIEW IF NOT EXISTS transactions AS SELECT * FROM borrowing_equipment');
    }
};
