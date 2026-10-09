<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function getLowStockItems(?int $campusId = null): Collection
    {
        return EquipmentInventory::with(['equipment', 'campus', 'location'])
            ->whereColumn('available_quantity', '<=', 'reorder_level')
            ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
            ->get();
    }

    public function getMostBorrowedEquipment(int $limit = 10, ?int $campusId = null): Collection
    {
        // With a campus: only that campus's stock and its borrowings count.
        $atCampus = fn ($q) => $q->where('campus_id', $campusId);

        return Equipment::withCount(['borrowingEquipment as total_borrowed' => function ($query) use ($campusId, $atCampus) {
            $query->select(\DB::raw('COALESCE(SUM(quantity), 0)'))
                ->when($campusId, fn ($q) => $q->whereHas('inventory', $atCampus));
        }])
            ->when($campusId, fn ($q) => $q->whereHas('inventory', $atCampus))
            ->orderByDesc('total_borrowed')
            ->limit($limit)
            ->get();
    }

    public function getAvailableEquipment(?int $campusId = null): Collection
    {
        $query = EquipmentInventory::with(['equipment', 'campus', 'location'])
            ->where('available_quantity', '>', 0);

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }

        return $query->get();
    }

    public function checkAvailability(int $equipmentId, int $inventoryId, int $quantity): bool
    {
        $inventory = EquipmentInventory::find($inventoryId);

        if (!$inventory || $inventory->equipment_id !== $equipmentId) {
            return false;
        }

        return $inventory->available_quantity >= $quantity;
    }

    public function getInventoryStats(?int $campusId = null): array
    {
        if ($campusId) {
            // One campus: its stock rows, and equipment stocked there.
            $inv = EquipmentInventory::where('campus_id', $campusId);
            $total = (int) (clone $inv)->sum('total_quantity');
            $available = (int) (clone $inv)->sum('available_quantity');

            return [
                'total_equipment' => Equipment::whereHas('inventory', fn ($q) => $q->where('campus_id', $campusId))->count(),
                'total_inventory' => $total,
                'available_inventory' => $available,
                'borrowed_inventory' => $total - $available,
                'low_stock_count' => (clone $inv)->whereColumn('available_quantity', '<=', 'reorder_level')->count(),
                'utilization_rate' => $total > 0 ? round((($total - $available) / $total) * 100, 2) : 0,
            ];
        }

        // Combines what used to be 4 separate queries (across 2 tables) into
        // a single round trip. Equipment uses soft deletes, so that subquery
        // excludes deleted_at the same way Equipment::count() already did;
        // equipment_inventory has no soft deletes, so none is needed there.
        $row = (array) DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM equipment WHERE deleted_at IS NULL) AS total_equipment,
                COALESCE(SUM(total_quantity), 0) AS total_inventory,
                COALESCE(SUM(available_quantity), 0) AS available_inventory,
                COUNT(CASE WHEN available_quantity <= reorder_level THEN 1 END) AS low_stock_count
            FROM equipment_inventory
        ");

        $totalInventory = (int) $row['total_inventory'];
        $availableInventory = (int) $row['available_inventory'];

        return [
            'total_equipment' => (int) $row['total_equipment'],
            'total_inventory' => $totalInventory,
            'available_inventory' => $availableInventory,
            'borrowed_inventory' => $totalInventory - $availableInventory,
            'low_stock_count' => (int) $row['low_stock_count'],
            'utilization_rate' => $totalInventory > 0
                ? round((($totalInventory - $availableInventory) / $totalInventory) * 100, 2)
                : 0,
        ];
    }
}
