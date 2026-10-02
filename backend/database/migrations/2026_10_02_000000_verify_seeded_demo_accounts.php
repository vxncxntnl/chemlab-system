<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The fixed set of demo accounts created by UserSeeder. These were
     * originally seeded without email_verified_at, so the 'verified'
     * middleware blocks them from reaching the dashboard even with the
     * correct password — the account exists, there's just nobody who can
     * click an emailed verification link for these addresses.
     */
    private array $seededEmails = [
        'admin@phinmacoc.edu.ph',
        'liza.macaraeg@phinmacoc.edu.ph',
        'ramon.durano@phinmacoc.edu.ph',
        'grace.buenaventura@phinmacoc.edu.ph',
        'maria.santos@phinmacoc.edu.ph',
        'jose.reyes@phinmacoc.edu.ph',
        'ana.villanueva@phinmacoc.edu.ph',
        'juan.delacruz@student.phinmacoc.edu.ph',
        'maria.reyes@student.phinmacoc.edu.ph',
        'carlos.buenaflor@student.phinmacoc.edu.ph',
        'anna.gomez@student.phinmacoc.edu.ph',
        'pedro.alvarez@student.phinmacoc.edu.ph',
    ];

    public function up(): void
    {
        DB::table('users')
            ->whereIn('email', $this->seededEmails)
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        DB::table('users')
            ->whereIn('email', $this->seededEmails)
            ->update(['email_verified_at' => null]);
    }
};
