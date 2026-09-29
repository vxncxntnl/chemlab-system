<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentInventory;
use Illuminate\Support\Collection;

class InventoryService
{
    public function getLowStockItems(): Collection
    {
        return EquipmentInventory::with(['equipment', 'campus', 'location'])
            ->whereColumn('available_quantity', '<=', 'reorder_level')
            ->get();
    }

    public function getMostBorrowedEquipment(int $limit = 10): Collection
    {
        return Equipment::withCount(['borrowingEquipment as total_borrowed' => function ($query) {
            $query->select(\DB::raw('COALESCE(SUM(quantity), 0)'));
        }])
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

    public function getInventoryStats(): array
    {
        $totalEquipment = Equipment::count();
        $totalInventory = EquipmentInventory::sum('total_quantity');
        $availableInventory = EquipmentInventory::sum('available_quantity');
        $lowStockCount = EquipmentInventory::whereColumn('available_quantity', '<=', 'reorder_level')->count();

        return [
            'total_equipment' => $totalEquipment,
            'total_inventory' => $totalInventory,
            'available_inventory' => $availableInventory,
            'borrowed_inventory' => $totalInventory - $availableInventory,
            'low_stock_count' => $lowStockCount,
            'utilization_rate' => $totalInventory > 0
                ? round((($totalInventory - $availableInventory) / $totalInventory) * 100, 2)
                : 0,
        ];
    }
}
