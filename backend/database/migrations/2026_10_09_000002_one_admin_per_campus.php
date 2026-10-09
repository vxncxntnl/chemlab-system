<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admins are per campus now (one active admin each), with Carmen as the main
 * campus. The existing admin(s) with no campus become Carmen's admin, and
 * the "one admin for the whole system" index is replaced by "one active
 * admin per campus". If a campus already has two active admins, the index is
 * skipped and a warning logged for review — no account is changed for that.
 */
return new class extends Migration
{
    public function up(): void
    {
        $carmen = DB::table('campuses')->where('campus_name', 'like', '%Carmen%')->orderBy('id')->value('id')
            ?? DB::table('campuses')->orderBy('id')->value('id');

        if ($carmen) {
            DB::table('users')->where('role', 'admin')->whereNull('campus_id')->update(['campus_id' => $carmen]);
        }

        DB::statement('DROP INDEX IF EXISTS users_one_admin');

        $shared = DB::table('users')
            ->select('campus_id')
            ->where('role', 'admin')->whereNull('deleted_at')->where('status', '!=', 'suspended')->whereNotNull('campus_id')
            ->groupBy('campus_id')->havingRaw('COUNT(*) > 1')
            ->pluck('campus_id');
        if ($shared->isNotEmpty()) {
            Log::warning('Not adding the one-admin-per-campus index: campus IDs ' . $shared->implode(', ') . ' have more than one active admin. Review them in Admin > Users.');
        } else {
            DB::statement("CREATE UNIQUE INDEX users_one_admin_per_campus ON users (campus_id) WHERE role = 'admin' AND deleted_at IS NULL AND status <> 'suspended'");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_one_admin_per_campus');
    }
};
