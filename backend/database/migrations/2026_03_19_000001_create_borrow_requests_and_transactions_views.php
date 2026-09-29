<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE VIEW borrow_requests AS SELECT * FROM borrowings');
        DB::statement('CREATE VIEW transactions AS SELECT * FROM borrowing_equipment');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS transactions');
        DB::statement('DROP VIEW IF EXISTS borrow_requests');
    }
};