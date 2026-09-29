<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->softDeletes();
        });

        // SQLite can't alter a table that a view depends on — the
        // borrow_requests view aliases borrowings, so it has to be dropped
        // before the alter and recreated after (same pattern used by the
        // earlier migrations that touched this table).
        DB::statement('DROP VIEW IF EXISTS borrow_requests');

        Schema::table('borrowings', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::statement('CREATE VIEW IF NOT EXISTS borrow_requests AS SELECT * FROM borrowings');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS borrow_requests');

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        DB::statement('CREATE VIEW IF NOT EXISTS borrow_requests AS SELECT * FROM borrowings');
    }
};
