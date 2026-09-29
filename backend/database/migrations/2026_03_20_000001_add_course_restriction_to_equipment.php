<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            // Comma-separated list of allowed departments/courses, e.g. "BSED,BSIT"
            // NULL means available to ALL students regardless of course.
            $table->string('course_restriction')->nullable()->after('description');
        });

        Schema::table('users', function (Blueprint $table) {
            // Store the student's enrolled course/program, e.g. "BSED", "BSIT", "BSN"
            $table->string('course')->nullable()->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('course_restriction');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('course');
        });
    }
};
