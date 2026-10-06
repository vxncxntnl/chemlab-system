<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The activity's existing activity_date is its borrow date; this adds
     * the date the equipment must be back. Nullable so activities created
     * before this column keep working (borrowers pick their own dates).
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->date('return_date')->nullable()->after('activity_date');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('return_date');
        });
    }
};
