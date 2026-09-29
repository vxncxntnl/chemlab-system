<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Alias existing borrowings table as borrow_requests
        DB::statement('CREATE VIEW IF NOT EXISTS borrow_requests AS SELECT * FROM borrowings');

        // Alias existing borrowing_equipment table as transactions
        DB::statement('CREATE VIEW IF NOT EXISTS transactions AS SELECT * FROM borrowing_equipment');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS transactions');
        DB::statement('DROP VIEW IF EXISTS borrow_requests');
    }
};

