<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance logs only pointed at an equipment record, which can be stocked
 * at several campuses — so a log couldn't be told apart by campus. Adds
 * campus_id and fills it for existing logs: the one campus stocking that
 * equipment; if several, the campus of the person who logged it (when that
 * campus stocks it); otherwise the main campus (Carmen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->foreignId('campus_id')->nullable()->after('equipment_id')->constrained('campuses')->nullOnDelete();
        });

        $carmen = DB::table('campuses')->where('campus_name', 'like', '%Carmen%')->orderBy('id')->value('id')
            ?? DB::table('campuses')->orderBy('id')->value('id');

        foreach (DB::table('maintenance_logs')->get(['id', 'equipment_id', 'performed_by']) as $log) {
            $stockedAt = DB::table('equipment_inventory')->where('equipment_id', $log->equipment_id)->distinct()->pluck('campus_id');
            $performerCampus = DB::table('users')->where('id', $log->performed_by)->value('campus_id');

            $campusId = match (true) {
                $stockedAt->count() === 1 => $stockedAt->first(),
                $performerCampus && $stockedAt->contains($performerCampus) => $performerCampus,
                default => $carmen,
            };

            DB::table('maintenance_logs')->where('id', $log->id)->update(['campus_id' => $campusId]);
        }
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campus_id');
        });
    }
};
