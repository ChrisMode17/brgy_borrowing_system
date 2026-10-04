<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\InventoryStatus;
use Illuminate\Support\Facades\DB;

/** Ported from backend/admin/manage_equipment_func.php */
class EquipmentService
{
    public const VALID_CATEGORIES = ['Equipment', 'Tool', 'Unit'];

    public static function getOrCreateCategoryId(string $category): ?int
    {
        $category = trim($category);
        if (!in_array($category, self::VALID_CATEGORIES, true)) {
            return null;
        }

        $existing = InventoryCategory::where('category_name', $category)->first();
        if ($existing) {
            return $existing->category_id;
        }

        return InventoryCategory::create(['category_name' => $category])->category_id;
    }

    public static function upsertStatus(int $equipmentId, string $status, int $quantity): bool
    {
        $quantity = max(0, $quantity);
        InventoryStatus::where('equipment_id', $equipmentId)->delete();

        if ($quantity <= 0) {
            return true;
        }

        $status = strtolower(trim($status));
        if (!in_array($status, ['available', 'under_maintenance', 'broken', 'lost'], true)) {
            $status = 'available';
        }

        return (bool) InventoryStatus::create([
            'equipment_id' => $equipmentId,
            'status' => $status,
            'quantity' => $quantity,
        ]);
    }

    /** @return array{success:bool,message:string} */
    public static function insert(string $name, string $category, int $totalQuantity): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['success' => false, 'message' => 'Equipment name is required'];
        }
        if (!in_array($category, self::VALID_CATEGORIES, true)) {
            return ['success' => false, 'message' => 'Category must be one of: ' . implode(', ', self::VALID_CATEGORIES)];
        }
        if ($totalQuantity < 1) {
            return ['success' => false, 'message' => 'Total quantity must be at least 1'];
        }

        $categoryId = self::getOrCreateCategoryId($category);
        if (!$categoryId) {
            return ['success' => false, 'message' => 'Invalid category'];
        }

        $item = Inventory::create([
            'name' => $name,
            'category_id' => $categoryId,
            'total_quantity' => $totalQuantity,
        ]);

        self::upsertStatus($item->equipment_id, 'available', $totalQuantity);

        return ['success' => true, 'message' => 'Equipment added successfully'];
    }

    /** @return array{success:bool,message:string} */
    public static function update(int $equipmentId, string $name, string $category, int $totalQuantity, int $availableQuantity): array
    {
        $name = trim($name);
        if ($equipmentId <= 0) {
            return ['success' => false, 'message' => 'Equipment ID is required'];
        }
        if ($name === '') {
            return ['success' => false, 'message' => 'Equipment name is required'];
        }
        if ($totalQuantity < 1) {
            return ['success' => false, 'message' => 'Total quantity must be at least 1'];
        }
        if ($availableQuantity < 0 || $availableQuantity > $totalQuantity) {
            return ['success' => false, 'message' => 'Available quantity must be between 0 and total quantity'];
        }

        $categoryId = self::getOrCreateCategoryId($category);
        if (!$categoryId) {
            return ['success' => false, 'message' => 'Invalid category'];
        }

        $item = Inventory::find($equipmentId);
        if (!$item) {
            return ['success' => false, 'message' => 'Equipment not found'];
        }

        $item->update(['name' => $name, 'category_id' => $categoryId, 'total_quantity' => $totalQuantity]);
        self::upsertStatus($equipmentId, 'available', $availableQuantity);

        return ['success' => true, 'message' => 'Equipment updated successfully'];
    }

    public static function delete(int $equipmentId): array
    {
        if ($equipmentId <= 0) {
            return ['success' => false, 'message' => 'Equipment ID is required'];
        }

        return DB::transaction(function () use ($equipmentId) {
            InventoryStatus::where('equipment_id', $equipmentId)->delete();
            $deleted = Inventory::where('equipment_id', $equipmentId)->delete();

            if (!$deleted) {
                return ['success' => false, 'message' => 'Equipment ID not found'];
            }

            return ['success' => true, 'message' => 'Equipment deleted successfully'];
        });
    }

    /**
     * Mark a quantity of "under maintenance" units as fixed, returning them as
     * available stock to the item they originally split off from (tracked via
     * source_equipment_id). Falls back to returning to the same item.
     */
    public static function fixMaintenance(int $equipmentId, int $fixQty): array
    {
        if ($equipmentId <= 0) {
            return ['success' => false, 'message' => 'Equipment ID is required'];
        }
        if ($fixQty <= 0) {
            return ['success' => false, 'message' => 'Quantity to fix must be greater than 0'];
        }

        $item = Inventory::find($equipmentId);
        if (!$item) {
            return ['success' => false, 'message' => 'Equipment not found'];
        }

        $maintenanceRow = InventoryStatus::where('equipment_id', $equipmentId)->where('status', 'under_maintenance')->first();
        $maintenanceQty = $maintenanceRow?->quantity ?? 0;

        if ($maintenanceQty <= 0) {
            return ['success' => false, 'message' => 'This item has no units currently under maintenance'];
        }
        if ($fixQty > $maintenanceQty) {
            return ['success' => false, 'message' => "Only {$maintenanceQty} unit(s) are under maintenance"];
        }

        $sourceId = $item->source_equipment_id;
        if (!$sourceId) {
            $sourceId = Inventory::where('name', trim($item->name))
                ->where('category_id', $item->category_id)
                ->where('equipment_id', '!=', $equipmentId)
                ->orderBy('equipment_id')
                ->value('equipment_id');
        }
        $returnTo = $sourceId ?: $equipmentId;

        return DB::transaction(function () use ($equipmentId, $fixQty, $returnTo, $maintenanceQty) {
            InventoryAvailabilityService::adjustQuantity($equipmentId, 'under_maintenance', -$fixQty);

            Inventory::where('equipment_id', $equipmentId)->update([
                'total_quantity' => DB::raw("GREATEST(0, total_quantity - {$fixQty})"),
            ]);
            Inventory::where('equipment_id', $returnTo)->increment('total_quantity', $fixQty);

            InventoryAvailabilityService::adjustQuantity($returnTo, 'available', $fixQty);

            $deletedEntry = false;
            if ($returnTo !== $equipmentId) {
                $remaining = Inventory::where('equipment_id', $equipmentId)->value('total_quantity');
                if ((int) $remaining <= 0) {
                    InventoryStatus::where('equipment_id', $equipmentId)->delete();
                    Inventory::where('equipment_id', $equipmentId)->delete();
                    $deletedEntry = true;
                }
            }

            return [
                'success' => true,
                'message' => $deletedEntry
                    ? "{$fixQty} unit(s) marked as fixed and returned to inventory. This maintenance entry is now empty and was removed."
                    : "{$fixQty} unit(s) marked as fixed and returned to inventory",
                'fixedQuantity' => $fixQty,
                'remainingMaintenance' => $maintenanceQty - $fixQty,
                'returnedToEquipmentId' => $returnTo,
                'entryDeleted' => $deletedEntry,
            ];
        });
    }
}
