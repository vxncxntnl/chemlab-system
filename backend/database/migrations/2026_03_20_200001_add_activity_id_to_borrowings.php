<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support altering a table when views reference it.
        // Drop the view, alter the table, then recreate the view.
        DB::statement('DROP VIEW IF EXISTS borrow_requests');

        Schema::table('borrowings', function (Blueprint $table) {
            $table->unsignedBigInteger('activity_id')->nullable()->after('faculty_id');
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('set null');
        });

        DB::statement('CREATE VIEW IF NOT EXISTS borrow_requests AS SELECT * FROM borrowings');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS borrow_requests');

        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn('activity_id');
        });

        DB::statement('CREATE VIEW IF NOT EXISTS borrow_requests AS SELECT * FROM borrowings');
    }
};
