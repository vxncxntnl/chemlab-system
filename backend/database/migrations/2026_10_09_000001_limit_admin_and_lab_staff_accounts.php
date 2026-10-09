<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Database-level backstop for the account limits enforced in
 * Admin\UserController: one admin system-wide, and at most one active
 * (not deleted, not suspended) lab staff account per campus.
 *
 * Partial unique indexes (PostgreSQL and SQLite both support them). No rows
 * are changed. If duplicates already exist, that index is skipped and a
 * warning logged for review — accounts are never deleted or demoted here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $admins = DB::table('users')->where('role', 'admin')->whereNull('deleted_at')->count();
        if ($admins > 1) {
            Log::warning("Not adding the one-admin index: {$admins} admin accounts exist. Review them in Admin > Users.");
        } else {
            DB::statement("CREATE UNIQUE INDEX users_one_admin ON users (role) WHERE role = 'admin' AND deleted_at IS NULL");
        }

        $sharedCampuses = DB::table('users')
            ->select('campus_id')
            ->where('role', 'lab_staff')->whereNull('deleted_at')->where('status', '!=', 'suspended')->whereNotNull('campus_id')
            ->groupBy('campus_id')->havingRaw('COUNT(*) > 1')
            ->pluck('campus_id');
        if ($sharedCampuses->isNotEmpty()) {
            Log::warning('Not adding the one-lab-staff-per-campus index: campus IDs ' . $sharedCampuses->implode(', ') . ' have more than one active lab staff account. Review them in Admin > Users.');
        } else {
            DB::statement("CREATE UNIQUE INDEX users_one_lab_staff_per_campus ON users (campus_id) WHERE role = 'lab_staff' AND deleted_at IS NULL AND status <> 'suspended'");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_one_admin');
        DB::statement('DROP INDEX IF EXISTS users_one_lab_staff_per_campus');
    }
};
