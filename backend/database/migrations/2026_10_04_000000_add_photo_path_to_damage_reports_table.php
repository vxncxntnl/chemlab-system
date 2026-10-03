<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            // Nullable — the photo is optional. Stored on the private 'local'
            // disk (not 'public'), unlike equipment images, since a damage
            // report can show a student's name/context and shouldn't be
            // reachable by anyone with a guessed URL.
            $table->string('photo_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
