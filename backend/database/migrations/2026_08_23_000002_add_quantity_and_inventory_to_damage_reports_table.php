<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->after('equipment_id');
            $table->foreignId('equipment_inventory_id')->nullable()->after('quantity')
                ->constrained('equipment_inventory')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_inventory_id');
            $table->dropColumn('quantity');
        });
    }
};
