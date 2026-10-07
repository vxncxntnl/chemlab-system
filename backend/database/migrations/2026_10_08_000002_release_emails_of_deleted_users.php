<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Accounts deleted before User::booted() started releasing emails still
     * hold theirs, blocking anyone from reusing that address. Release them
     * the same way: "original#deleted-{id}". Only already-deleted rows are
     * touched; their history (borrowings, audit logs) is unaffected.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->where('email', 'not like', '%#deleted-%')
            ->orderBy('id')
            ->get(['id', 'email'])
            ->each(fn ($row) => DB::table('users')->where('id', $row->id)->update([
                'email'     => $row->email . '#deleted-' . $row->id,
                'google_id' => null,
            ]));
    }

    public function down(): void
    {
        // Not reversed: the original address may have been reused since.
    }
};
