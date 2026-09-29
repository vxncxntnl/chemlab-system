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

        // Drop the view before altering borrowings.
        DB::statement('DROP VIEW IF EXISTS borrow_requests');

        Schema::table('borrowings', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::statement('CREATE VIEW borrow_requests AS SELECT * FROM borrowings');
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

        DB::statement('CREATE VIEW borrow_requests AS SELECT * FROM borrowings');
    }
};