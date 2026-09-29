<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'lab_staff', 'faculty', 'student'])->default('student')->after('email');
            $table->string('department')->nullable()->after('role');
            $table->string('student_id')->nullable()->after('department');
            $table->string('employee_id')->nullable()->after('student_id');
            $table->string('contact_number')->nullable()->after('employee_id');
            $table->foreignId('campus_id')->nullable()->after('contact_number')->constrained('campuses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campus_id');
            $table->dropColumn(['role', 'department', 'student_id', 'employee_id', 'contact_number']);
        });
    }
};
