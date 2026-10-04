<?php

namespace App\Services;

use App\Models\BorrowItem;
use App\Models\Inventory;
use App\Models\InventoryStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ported from backend/inventory/transactions.php.
 * Availability is derived from inventory_status buckets (available /
 * under_maintenance / broken / lost), never stored directly on `inventory`.
 */
class InventoryAvailabilityService
{
    public static function normalizeStatusKey(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return match ($normalized) {
            'available' => 'available',
            'undermaintenance', 'under_maintenance', 'under maintenanced', 'maintenance' => 'under_maintenance',
            'broken' => 'broken',
            'lost' => 'lost',
            default => 'borrowed',
        };
    }

    public static function statusLabel(string $statusKey): string
    {
        return match ($statusKey) {
            'available' => 'Available',
            'under_maintenance' => 'Under Maintained',
            'broken' => 'Broken',
            'lost' => 'Lost',
            default => 'Borrowed',
        };
    }

    /**
     * @return array{totalQuantity:int,availableQuantity:int,maintenanceQuantity:int,brokenQuantity:int,lostQuantity:int,statusKey:string,statusLabel:string}|null
     */
    public static function snapshot(int $equipmentId): ?array
    {
        $inventory = Inventory::find($equipmentId);
        if (!$inventory) {
            return null;
        }

        $buckets = ['available' => 0, 'under_maintenance' => 0, 'broken' => 0, 'lost' => 0];
        foreach (InventoryStatus::where('equipment_id', $equipmentId)->get() as $row) {
            $key = self::normalizeStatusKey($row->status);
            if (isset($buckets[$key])) {
                $buckets[$key] += max(0, (int) $row->quantity);
            }
        }

        $statusKey = match (true) {
            $buckets['available'] > 0 => 'available',
            $buckets['under_maintenance'] > 0 => 'under_maintenance',
            $buckets['broken'] > 0 => 'broken',
            $buckets['lost'] > 0 => 'lost',
            default => 'borrowed',
        };

        return [
            'totalQuantity' => (int) $inventory->total_quantity,
            'availableQuantity' => $buckets['available'],
            'maintenanceQuantity' => $buckets['under_maintenance'],
            'brokenQuantity' => $buckets['broken'],
            'lostQuantity' => $buckets['lost'],
            'statusKey' => $statusKey,
            'statusLabel' => self::statusLabel($statusKey),
        ];
    }

    /**
     * Increment/decrement a status bucket for an item, creating the row if needed.
     * Never lets quantity go below zero.
     */
    public static function adjustQuantity(int $equipmentId, string $status, int $delta): bool
    {
        if ($delta === 0) {
            return true;
        }

        $status = strtolower(trim($status));

        // Composite primary key (equipment_id, status) -- use the query builder
        // directly rather than Eloquent's save(), which assumes a single key column.
        $query = InventoryStatus::where('equipment_id', $equipmentId)->where('status', $status);

        if ($query->exists()) {
            return (bool) $query->update(['quantity' => DB::raw("GREATEST(0, quantity + ({$delta}))")]);
        }

        return (bool) InventoryStatus::create([
            'equipment_id' => $equipmentId,
            'status' => $status,
            'quantity' => max(0, $delta),
        ]);
    }

    /**
     * Available quantity including items that will be returned on/before $borrowDate,
     * minus overlapping pending reservations in the requested window.
     *
     * @return array{currentAvailable:int,futureReturns:int,reservedOverlap:int,effectiveAvailable:int}|null
     */
    public static function effectiveAvailability(int $equipmentId, ?string $borrowDate = null, ?string $returnDate = null, ?string $excludeRefnum = null): ?array
    {
        $snapshot = self::snapshot($equipmentId);
        if (!$snapshot) {
            return null;
        }

        $currentAvailable = $snapshot['availableQuantity'];
        $futureReturns = 0;
        $reservedOverlap = 0;

        if (!empty($borrowDate)) {
            $futureReturns = (int) BorrowItem::query()
                ->join('transactions', 'borrow_items.refnum', '=', 'transactions.refnum')
                ->where('borrow_items.equipment_id', $equipmentId)
                ->where('transactions.status', 'borrowed')
                ->where('transactions.returnDate', '<=', $borrowDate)
                ->sum('borrow_items.quantity');
        }

        $effectiveAvailable = $currentAvailable + $futureReturns;

        if (!empty($borrowDate) && !empty($returnDate)) {
            $query = BorrowItem::query()
                ->join('transactions', 'borrow_items.refnum', '=', 'transactions.refnum')
                ->where('borrow_items.equipment_id', $equipmentId)
                ->where('transactions.status', 'pending')
                ->where(function ($q) use ($borrowDate, $returnDate) {
                    // NOT (returnDate <= borrowDate OR dateBorrowed >= returnDate)
                    $q->where('transactions.returnDate', '>', $borrowDate)
                        ->where('transactions.dateBorrowed', '<', $returnDate);
                });

            if (!empty($excludeRefnum)) {
                $query->where('transactions.refnum', '!=', $excludeRefnum);
            }

            $reservedOverlap = (int) $query->sum('borrow_items.quantity');
            $effectiveAvailable = max(0, $effectiveAvailable - $reservedOverlap);
        }

        return [
            'currentAvailable' => $currentAvailable,
            'futureReturns' => $futureReturns,
            'reservedOverlap' => $reservedOverlap,
            'effectiveAvailable' => $effectiveAvailable,
        ];
    }
}
