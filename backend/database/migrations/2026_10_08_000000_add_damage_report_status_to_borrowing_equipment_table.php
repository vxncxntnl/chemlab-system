<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks a student's "Report Damage" tap on one borrowed item through to
     * the lab staff's decision:
     *   reported  → student tapped Report Damage, waiting for lab staff
     *   confirmed → lab staff recorded a damage report for it
     *   no_damage → lab staff checked it and found no damage
     * All nullable: existing rows simply have no report.
     */
    public function up(): void
    {
        Schema::table('borrowing_equipment', function (Blueprint $table) {
            $table->string('damage_report_status')->nullable();
            $table->timestamp('damage_reported_at')->nullable();
            $table->string('damage_report_note', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('borrowing_equipment', function (Blueprint $table) {
            $table->dropColumn(['damage_report_status', 'damage_reported_at', 'damage_report_note']);
        });
    }
};
