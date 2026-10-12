<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Serial numbers per unit, for equipment that can be repaired/calibrated
 * (instruments, electronics). Glassware and simple tools are still counted
 * by quantity only. Each unit has a system tag (e.g. EQP-00014-01) and an
 * optional factory serial number; a maintenance log can name the exact unit,
 * which then shows as "Under Maintenance" while that log is open.
 *
 * The listed instruments are switched on and get one unit per item in
 * stock; an existing serial number goes on the first unit. Nothing else
 * changes (stock quantities stay the same).
 */
return new class extends Migration
{
    private const TRACKED = [
        'ac adaptor', 'analog ammeter', 'analytical balance (0.0001 g)', 'calipers', 'compound microscope',
        'digital ph meter', 'em-3536 essential physics modular circuits kit', 'ex-5534 resistivity set',
        'micrometer', 'optical bench', 'sliding rheostat', 'triple beam balance',
    ];

    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->boolean('tracks_units')->default(false)->after('serial_number');
        });

        Schema::create('equipment_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            // The campus stock it belongs to.
            $table->foreignId('equipment_inventory_id')->constrained('equipment_inventory')->cascadeOnDelete();
            $table->string('unit_code', 40)->unique();
            $table->string('serial_number', 100)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->foreignId('equipment_unit_id')->nullable()->after('equipment_id')->constrained('equipment_units')->nullOnDelete();
        });

        $tracked = DB::table('equipment')->whereNull('deleted_at')->get(['id', 'name', 'equipment_code', 'serial_number'])
            ->filter(fn ($e) => in_array(mb_strtolower(trim($e->name)), self::TRACKED, true));

        foreach ($tracked as $equipment) {
            DB::table('equipment')->where('id', $equipment->id)->update(['tracks_units' => true]);

            $n = 0;
            $serial = $equipment->serial_number ?: null;
            foreach (DB::table('equipment_inventory')->where('equipment_id', $equipment->id)->orderBy('id')->get() as $inventory) {
                for ($i = 0; $i < (int) $inventory->total_quantity; $i++) {
                    $n++;
                    DB::table('equipment_units')->insert([
                        'equipment_id' => $equipment->id,
                        'equipment_inventory_id' => $inventory->id,
                        'unit_code' => sprintf('%s-%02d', $equipment->equipment_code, $n),
                        'serial_number' => $n === 1 ? $serial : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_unit_id');
        });
        Schema::dropIfExists('equipment_units');
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('tracks_units');
        });
    }
};
