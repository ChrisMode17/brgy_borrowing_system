<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\BorrowItem;
use App\Models\Inventory;
use App\Models\ReturnIssue;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ported from backend/inventory/transactions.php and backend/inventory/reserve.php.
 * Covers reserving, confirming (borrow), returning (full + partial), and
 * housekeeping (late status, stale pending cleanup).
 */
class TransactionService
{
    public static function findOrCreateBorrower(string $fname, string $lname, string $address, string $contact): int
    {
        return Borrower::firstOrCreate(
            [
                'first_name' => $fname,
                'last_name' => $lname,
                'contact' => $contact,
            ],
            ['address' => $address],
        )->borrower_id;
    }

    /**
     * Create a pending reservation with one or more items.
     * $items = [['equipment_id' => int, 'quantity' => int], ...]
     *
     * @return array{success:bool,message:string,refnum?:string}
     */
    public static function reserve(string $fname, string $lname, string $address, string $contact, string $dateBorrowedDb, string $returnDateDb, array $items): array
    {
        if (empty($items)) {
            return ['success' => false, 'message' => 'Please add at least one item.'];
        }

        $quantitiesByEquipment = [];
        foreach ($items as $row) {
            $equipmentId = (int) $row['equipment_id'];
            $quantity = (int) $row['quantity'];
            if ($quantity <= 0) {
                return ['success' => false, 'message' => 'Each item quantity must be greater than zero.'];
            }

            $quantitiesByEquipment[$equipmentId] = ($quantitiesByEquipment[$equipmentId] ?? 0) + $quantity;
        }
        ksort($quantitiesByEquipment, SORT_NUMERIC);

        return DB::transaction(function () use ($fname, $lname, $address, $contact, $dateBorrowedDb, $returnDateDb, $quantitiesByEquipment) {
            $inventories = [];
            foreach (array_keys($quantitiesByEquipment) as $equipmentId) {
                $inventory = Inventory::query()
                    ->whereKey($equipmentId)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    throw new \RuntimeException('One of the selected items no longer exists.');
                }

                $inventories[$equipmentId] = $inventory;
            }

            foreach ($quantitiesByEquipment as $equipmentId => $quantity) {
                $availability = InventoryAvailabilityService::effectiveAvailability($equipmentId, $dateBorrowedDb, $returnDateDb);
                $effectiveAvailable = $availability['effectiveAvailable'] ?? 0;

                if ($quantity <= 0 || $quantity > $effectiveAvailable) {
                    $inventory = $inventories[$equipmentId];
                    $msg = $effectiveAvailable <= 0
                        ? "Cannot reserve {$quantity} {$inventory->name}. No units are available during your requested borrow dates due to other reservations."
                        : "Cannot reserve {$quantity} {$inventory->name}. Only {$effectiveAvailable} unit(s) available during your requested borrow dates.";
                    throw new \RuntimeException($msg);
                }
            }

            $refnum = ReferenceNumberGenerator::generate();
            $borrowerId = self::findOrCreateBorrower($fname, $lname, $address, $contact);

            Transaction::create([
                'refnum' => $refnum,
                'borrower_id' => $borrowerId,
                'dateBorrowed' => $dateBorrowedDb,
                'returnDate' => $returnDateDb,
                'status' => 'pending',
            ]);

            $summaries = [];
            foreach ($quantitiesByEquipment as $equipmentId => $quantity) {
                BorrowItem::create(['refnum' => $refnum, 'equipment_id' => $equipmentId, 'quantity' => $quantity]);
                $inventory = $inventories[$equipmentId];
                $summaries[] = "{$inventory->name} x{$quantity}";
            }

            AuditLogger::log(
                'Reserved equipment',
                'Equipment',
                sprintf(
                    'Ref: %s | Borrower: %s %s | Contact: %s | Items: %s | Borrow Date: %s | Return Date: %s',
                    $refnum,
                    $fname,
                    $lname,
                    $contact,
                    implode(', ', $summaries),
                    $dateBorrowedDb,
                    $returnDateDb
                )
            );

            return ['success' => true, 'message' => 'Reservation saved successfully.', 'refnum' => $refnum];
        });
    }

    /** Confirm a pending reservation: deduct inventory and mark as 'borrowed'. Only allowed on/after the scheduled borrow date. */
    public static function confirmBorrow(string $refnum): array
    {
        $transaction = Transaction::with('borrower')->find($refnum);
        if (! $transaction) {
            return ['success' => false, 'message' => 'Transaction not found.'];
        }

        $scheduledBorrowAt = Carbon::parse($transaction->dateBorrowed, config('app.timezone'));
        if (now(config('app.timezone'))->lt($scheduledBorrowAt)) {
            return ['success' => false, 'message' => 'Borrow is only available on or after the scheduled borrow date and time.'];
        }

        $items = BorrowItem::with('inventory')->where('refnum', $refnum)->where('quantity', '>', 0)->get();
        if ($items->isEmpty()) {
            return ['success' => false, 'message' => 'No items found for this transaction'];
        }

        return DB::transaction(function () use ($refnum, $transaction, $items) {
            $equipmentIds = $items->pluck('equipment_id')->filter()->unique()->sort()->values();
            foreach ($equipmentIds as $equipmentId) {
                Inventory::query()
                    ->whereKey($equipmentId)
                    ->lockForUpdate()
                    ->first();
            }

            foreach ($items as $item) {
                $availability = InventoryAvailabilityService::effectiveAvailability(
                    $item->equipment_id,
                    (string) $transaction->dateBorrowed,
                    (string) $transaction->returnDate,
                    $refnum
                );
                $effectiveAvailable = $availability['effectiveAvailable'] ?? 0;
                if ($effectiveAvailable < $item->quantity) {
                    throw new \RuntimeException("Insufficient quantity for {$item->inventory?->name} (only {$effectiveAvailable} available for your requested date range).");
                }
            }

            $summaries = [];
            foreach ($items as $item) {
                InventoryAvailabilityService::adjustQuantity($item->equipment_id, 'available', -$item->quantity);
                $summaries[] = "{$item->inventory?->name} x{$item->quantity}";
            }

            $transaction->update(['status' => 'borrowed']);

            AuditLogger::log(
                'Borrowed equipment',
                'Equipment',
                sprintf(
                    'Ref: %s | Borrower: %s %s | Address: %s | Contact: %s | Items: %s | Borrow Date: %s | Return Date: %s',
                    $refnum,
                    $transaction->borrower?->first_name,
                    $transaction->borrower?->last_name,
                    $transaction->borrower?->address,
                    $transaction->borrower?->contact,
                    implode(', ', $summaries),
                    $transaction->dateBorrowed,
                    $transaction->returnDate
                )
            );

            return ['success' => true, 'message' => 'Transaction marked as borrowed and inventory updated'];
        });
    }

    /** Full return: restores all borrowed quantities to 'available' and marks transaction returned. */
    public static function returnAll(string $refnum): array
    {
        $transaction = Transaction::with('borrower')->find($refnum);
        if (! $transaction) {
            return ['success' => false, 'message' => 'Transaction not found'];
        }

        $items = BorrowItem::with('inventory')->where('refnum', $refnum)->get();

        return DB::transaction(function () use ($refnum, $transaction, $items) {
            $summaries = [];
            foreach ($items as $item) {
                InventoryAvailabilityService::adjustQuantity($item->equipment_id, 'available', $item->quantity);
                $summaries[] = "{$item->inventory?->name} x{$item->quantity}";
            }

            $transaction->update(['status' => 'returned']);

            AuditLogger::log(
                'Returned equipment',
                'Equipment',
                sprintf(
                    'Ref: %s | Borrower: %s %s | Address: %s | Contact: %s | Items: %s | Note: All items are returned properly.',
                    $refnum,
                    $transaction->borrower?->first_name,
                    $transaction->borrower?->last_name,
                    $transaction->borrower?->address,
                    $transaction->borrower?->contact,
                    implode(', ', $summaries)
                )
            );

            return ['success' => true, 'message' => 'Equipment returned successfully'];
        });
    }

    /**
     * Move maintenance-flagged units into their own inventory "twin" item, linked
     * via source_equipment_id, so Manage Equipment tracks them separately.
     */
    public static function ensureMaintenanceEntry(int $baseEquipmentId, int $maintenanceQty): bool
    {
        if ($maintenanceQty <= 0) {
            return true;
        }

        $base = Inventory::find($baseEquipmentId);
        if (! $base) {
            return false;
        }

        Inventory::where('equipment_id', $baseEquipmentId)->update([
            'total_quantity' => DB::raw("GREATEST(0, total_quantity - {$maintenanceQty})"),
        ]);

        $existing = Inventory::query()
            ->join('inventory_status', function ($join) {
                $join->on('inventory_status.equipment_id', '=', 'inventory.equipment_id')
                    ->where('inventory_status.status', 'under_maintenance');
            })
            ->where('inventory.equipment_id', '!=', $baseEquipmentId)
            ->where(function ($q) use ($baseEquipmentId, $base) {
                $q->where('inventory.source_equipment_id', $baseEquipmentId)
                    ->orWhere(function ($q2) use ($base) {
                        $q2->whereNull('inventory.source_equipment_id')
                            ->where('inventory.name', trim($base->name))
                            ->where('inventory.category_id', $base->category_id);
                    });
            })
            ->select('inventory.*')
            ->first();

        if ($existing) {
            $existing->increment('total_quantity', $maintenanceQty);

            return InventoryAvailabilityService::adjustQuantity($existing->equipment_id, 'under_maintenance', $maintenanceQty);
        }

        $new = Inventory::create([
            'name' => trim($base->name) !== '' ? trim($base->name) : 'Item',
            'category_id' => $base->category_id,
            'total_quantity' => $maintenanceQty,
            'source_equipment_id' => $baseEquipmentId,
        ]);

        return InventoryAvailabilityService::adjustQuantity($new->equipment_id, 'under_maintenance', $maintenanceQty);
    }

    /**
     * Partial return: per borrow_item, split returned quantity into normal / broken / maintenance.
     * $returns = [borrow_item_id => ['normal' => int, 'broken' => int, 'maintenance' => int], ...]
     */
    public static function partialReturn(string $refnum, array $returns): array
    {
        $returns = array_filter($returns, fn ($r) => (($r['normal'] ?? 0) + ($r['broken'] ?? 0) + ($r['maintenance'] ?? 0)) > 0);
        if (empty($returns)) {
            return ['success' => false, 'message' => 'No return quantities provided.'];
        }

        try {
            return DB::transaction(function () use ($refnum, $returns) {
                $processed = [];

                foreach ($returns as $borrowItemId => $qty) {
                    $normal = (int) ($qty['normal'] ?? 0);
                    $broken = (int) ($qty['broken'] ?? 0);
                    $maintenance = (int) ($qty['maintenance'] ?? 0);
                    $total = $normal + $broken + $maintenance;
                    if ($total <= 0) {
                        continue;
                    }

                    $item = BorrowItem::where('id', $borrowItemId)->where('refnum', $refnum)->first();
                    if (! $item || $total > $item->quantity) {
                        throw new \RuntimeException('Failed to process partial return.');
                    }

                    $inventory = Inventory::find($item->equipment_id);
                    $itemName = $inventory?->name ?? 'Unknown Item';

                    if ($normal > 0) {
                        InventoryAvailabilityService::adjustQuantity($item->equipment_id, 'available', $normal);
                    }
                    if ($broken > 0) {
                        Inventory::where('equipment_id', $item->equipment_id)->update([
                            'total_quantity' => DB::raw("GREATEST(0, total_quantity - {$broken})"),
                        ]);
                    }
                    if ($maintenance > 0) {
                        self::ensureMaintenanceEntry($item->equipment_id, $maintenance);
                    }

                    $item->update(['quantity' => max(0, $item->quantity - $total)]);
                    $processed[] = sprintf('%s (Good: %d, Maintenance: %d, Broken: %d)', $itemName, $normal, $maintenance, $broken);

                    if ($broken > 0 || $maintenance > 0) {
                        ReturnIssue::create([
                            'borrow_item_id' => $item->id,
                            'normal_qty' => $normal,
                            'broken_qty' => $broken,
                            'maintenance_qty' => $maintenance,
                            'issue_status' => $normal > 0 ? 'partial' : 'issue',
                            'created_at' => now(),
                        ]);
                    }
                }

                $remainingCount = BorrowItem::where('refnum', $refnum)->where('quantity', '>', 0)->count();
                $transaction = Transaction::with('borrower')->find($refnum);

                if ($remainingCount === 0) {
                    $transaction->update(['status' => 'returned']);
                } else {
                    $status = now()->gt($transaction->returnDate) ? 'late' : 'borrowed';
                    $transaction->update(['status' => $status]);
                }

                AuditLogger::log(
                    'Returned equipment',
                    'Equipment',
                    sprintf(
                        'Ref: %s | Borrower: %s %s | Address: %s | Contact: %s | Items: %s | Note: Partial return processed. Some items were good, needed maintenance, or were broken.',
                        $refnum,
                        $transaction->borrower?->first_name,
                        $transaction->borrower?->last_name,
                        $transaction->borrower?->address,
                        $transaction->borrower?->contact,
                        implode(', ', $processed)
                    )
                );

                return [
                    'success' => true,
                    'message' => $remainingCount === 0 ? 'All items returned successfully.' : 'Partial return successful. Remaining borrowed quantity still active.',
                ];
            });
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Flip 'borrowed' transactions past their returnDate to 'late', logging each. */
    public static function updateLateTransactions(): int
    {
        $now = now();
        $late = Transaction::with('borrower')->where('returnDate', '<', $now)->where('status', 'borrowed')->get();

        foreach ($late as $transaction) {
            AuditLogger::system(
                'Auto Late Status of Return',
                sprintf(
                    'Auto Late Status of Return Equipment passed due date. Ref: %s | Borrower: %s %s | Due Date: %s | Action Time: %s',
                    $transaction->refnum,
                    $transaction->borrower?->first_name,
                    $transaction->borrower?->last_name,
                    $transaction->returnDate,
                    $now
                )
            );
        }

        return Transaction::where('returnDate', '<', $now)->where('status', 'borrowed')->update(['status' => 'late']);
    }

    /** Delete pending reservations left unclaimed for more than 24 hours. */
    public static function deleteOldPendingTransactions(): void
    {
        $cutoff = now()->subHours(24);
        $stale = Transaction::where('status', 'pending')->where('dateBorrowed', '<', $cutoff)->get();

        foreach ($stale as $transaction) {
            AuditLogger::system('Auto-cancelled reserve transaction', 'Auto Cancelation of unclaimed reserve transaction');
            BorrowItem::where('refnum', $transaction->refnum)->delete();
            $transaction->delete();
        }
    }
}
