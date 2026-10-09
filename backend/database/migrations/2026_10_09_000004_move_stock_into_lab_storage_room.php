<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Each campus keeps all its equipment in one room, its "Lab Storage Room".
 * Moves every stock row there. When the same equipment is already stocked in
 * that room, the quantities are added together into that one row, and
 * everything pointing at the old row (borrowed items, stock adjustments,
 * activity QR equipment, damage reports) is moved to it first — so no
 * borrowing history is lost — before the emptied row is removed.
 *
 * Other rooms stay in the database (just unused). A JSON backup of every
 * affected row is written to storage/app/backups first. Not reversible
 * automatically — restore from that backup if ever needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $campuses = DB::table('campuses')->get(['id', 'campus_name']);
        $backup = ['taken_at' => now()->toIso8601String(), 'campuses' => []];

        DB::transaction(function () use ($campuses, &$backup) {
            foreach ($campuses as $campus) {
                $room = $this->storageRoom($campus);

                $moving = DB::table('equipment_inventory')
                    ->where('campus_id', $campus->id)
                    ->where('location_id', '!=', $room)
                    ->orderBy('id')
                    ->get();

                if ($moving->isEmpty()) {
                    continue;
                }

                $backup['campuses'][$campus->campus_name] = [
                    'storage_room_id' => $room,
                    'rows_before' => DB::table('equipment_inventory')->where('campus_id', $campus->id)->get(),
                    'merged' => [],
                ];

                foreach ($moving as $row) {
                    $target = DB::table('equipment_inventory')
                        ->where('campus_id', $campus->id)
                        ->where('location_id', $room)
                        ->where('equipment_id', $row->equipment_id)
                        ->where('id', '!=', $row->id)
                        ->first();

                    if (!$target) {
                        DB::table('equipment_inventory')->where('id', $row->id)->update(['location_id' => $room, 'updated_at' => now()]);
                        continue;
                    }

                    // Same equipment already in the room: add the quantities together.
                    DB::table('equipment_inventory')->where('id', $target->id)->update([
                        'total_quantity' => $target->total_quantity + $row->total_quantity,
                        'available_quantity' => $target->available_quantity + $row->available_quantity,
                        'reorder_level' => max($target->reorder_level, $row->reorder_level),
                        'updated_at' => now(),
                    ]);

                    DB::table('borrowing_equipment')->where('inventory_id', $row->id)->update(['inventory_id' => $target->id]);
                    DB::table('stock_adjustments')->where('equipment_inventory_id', $row->id)->update(['equipment_inventory_id' => $target->id]);
                    DB::table('damage_reports')->where('equipment_inventory_id', $row->id)->update(['equipment_inventory_id' => $target->id]);
                    // An activity can list a stock row only once.
                    foreach (DB::table('activity_equipment_qr')->where('equipment_inventory_id', $row->id)->get() as $qr) {
                        $dup = DB::table('activity_equipment_qr')->where('activity_id', $qr->activity_id)->where('equipment_inventory_id', $target->id)->exists();
                        $dup
                            ? DB::table('activity_equipment_qr')->where('id', $qr->id)->delete()
                            : DB::table('activity_equipment_qr')->where('id', $qr->id)->update(['equipment_inventory_id' => $target->id]);
                    }

                    DB::table('equipment_inventory')->where('id', $row->id)->delete();
                    $backup['campuses'][$campus->campus_name]['merged'][] = ['from' => $row->id, 'into' => $target->id];
                }
            }
        });

        try {
            if (!app()->runningUnitTests() && $backup['campuses']) {
                Storage::disk('local')->put('backups/stock-before-lab-storage-room-' . now()->format('Ymd-His') . '.json', json_encode($backup, JSON_PRETTY_PRINT));
            }
        } catch (\Throwable $e) {
            report($e);
        }
        Log::info('Moved stock into each campus Lab Storage Room.', ['merged' => collect($backup['campuses'])->map(fn ($c) => $c['merged'])->all()]);
    }

    public function down(): void
    {
        // Not reversible automatically (rows may have been merged). The
        // backup JSON in storage/app/backups has the original rows.
    }

    private function storageRoom(object $campus): int
    {
        $existing = DB::table('locations')->where('campus_id', $campus->id)->where('location_name', 'like', 'Lab Storage Room%')->orderBy('id')->value('id');
        if ($existing) {
            return $existing;
        }

        $short = trim(preg_split('/[–—-]/u', $campus->campus_name)[1] ?? $campus->campus_name);

        return DB::table('locations')->insertGetId([
            'campus_id' => $campus->id,
            'location_name' => "Lab Storage Room – {$short}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
