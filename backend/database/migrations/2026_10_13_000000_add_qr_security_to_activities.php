<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QR security for lab activities (no school enrollment data is available,
 * so the instructor's approval is the source of truth):
 *  - activities: a start/end time (the QR only works during it), a "Live"
 *    classroom mode (the QR shown on screen changes every minute), and an
 *    optional class label (e.g. "Chem 101 – BSIT4-05") used to remember
 *    students the instructor already approved for that class;
 *  - activity_students: scanning now only asks to join ("pending") — the
 *    instructor approves. Existing rows stay approved, so nothing breaks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('activity_date');
            $table->time('end_time')->nullable()->after('start_time');
            // When the QR starts/stops working (from the date and times; the
            // instructor can extend the end). Null on older activities = no limit.
            $table->dateTime('qr_opens_at')->nullable()->after('qr_token');
            $table->dateTime('qr_closes_at')->nullable()->after('qr_opens_at');
            $table->boolean('qr_live')->default(false)->after('qr_closes_at');
            $table->string('class_label', 100)->nullable()->after('department');
        });

        Schema::table('activity_students', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->after('student_id');
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('activity_students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'approved_at']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'qr_opens_at', 'qr_closes_at', 'qr_live', 'class_label']);
        });
    }
};
