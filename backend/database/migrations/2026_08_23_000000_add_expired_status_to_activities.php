<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE activities DROP CONSTRAINT IF EXISTS activities_status_check'
            );

            DB::statement("
                ALTER TABLE activities
                ALTER COLUMN status TYPE varchar(255)
                USING status::text
            ");

            DB::statement("
                ALTER TABLE activities
                ALTER COLUMN status SET DEFAULT 'upcoming'
            ");

            DB::statement("
                ALTER TABLE activities
                ADD CONSTRAINT activities_status_check
                CHECK (status IN (
                    'upcoming',
                    'ongoing',
                    'completed',
                    'cancelled',
                    'expired'
                ))
            ");
        } else {
            Schema::table('activities', function (Blueprint $table) {
                $table->enum('status', [
                    'upcoming',
                    'ongoing',
                    'completed',
                    'cancelled',
                    'expired',
                ])->default('upcoming')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE activities DROP CONSTRAINT IF EXISTS activities_status_check'
            );

            DB::statement("
                ALTER TABLE activities
                ALTER COLUMN status TYPE varchar(255)
                USING status::text
            ");

            DB::statement("
                ALTER TABLE activities
                ALTER COLUMN status SET DEFAULT 'upcoming'
            ");

            DB::statement("
                ALTER TABLE activities
                ADD CONSTRAINT activities_status_check
                CHECK (status IN (
                    'upcoming',
                    'ongoing',
                    'completed',
                    'cancelled'
                ))
            ");
        } else {
            Schema::table('activities', function (Blueprint $table) {
                $table->enum('status', [
                    'upcoming',
                    'ongoing',
                    'completed',
                    'cancelled',
                ])->default('upcoming')->change();
            });
        }
    }
};