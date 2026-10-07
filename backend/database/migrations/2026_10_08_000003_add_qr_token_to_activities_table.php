<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * One QR code per activity (instead of one per equipment item). The QR
     * holds only this random token — a secure reference — never the
     * equipment list itself; the list stays in activity_equipment_qr.
     * Existing activities get a token too. Old per-item QR codes keep working.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique();
            $table->unsignedInteger('qr_scan_count')->default(0);
        });

        DB::table('activities')->whereNull('qr_token')->orderBy('id')->pluck('id')
            ->each(fn ($id) => DB::table('activities')->where('id', $id)->update(['qr_token' => Str::random(40)]));
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn(['qr_token', 'qr_scan_count']);
        });
    }
};
