<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Services\InventoryAvailabilityService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = InventoryCategory::orderBy('category_name')->get();

        return view('inventory.index', compact('categories'));
    }

    /** JSON feed for the inventory table, ported from get_inventory_ajax.php */
    public function data(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $category = trim((string) $request->query('category', ''));
        $borrowDate = $request->query('borrowDate');
        $returnDate = $request->query('returnDate');
        $page = max(1, (int) $request->query('page', 1));

        if ($borrowDate) {
            $borrowDate = str_replace('T', ' ', $borrowDate) . ':00';
        }
        if ($returnDate) {
            $returnDate = str_replace('T', ' ', $returnDate) . ':00';
        }

        $query = Inventory::query()->with('category');
        if ($category !== '') {
            $query->whereHas('category', fn($q) => $q->where('category_name', $category));
        }
        if ($search !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        }

        $items = [];
        foreach ($query->orderBy('name')->get() as $inventory) {
            $availability = InventoryAvailabilityService::snapshot($inventory->equipment_id);
            $statusKey = $availability['statusKey'] ?? 'borrowed';

            if ($status !== '' && InventoryAvailabilityService::normalizeStatusKey($status) !== $statusKey) {
                continue;
            }

            $futureAvailable = $availability['availableQuantity'] ?? 0;
            $futureReturns = 0;
            if ($borrowDate) {
                $window = InventoryAvailabilityService::effectiveAvailability($inventory->equipment_id, $borrowDate, $returnDate);
                if ($window) {
                    $futureReturns = $window['futureReturns'];
                    $futureAvailable = $window['effectiveAvailable'];
                }
            }

            $items[] = [
                'id' => $inventory->equipment_id,
                'name' => $inventory->name,
                'availableQuantity' => $availability['availableQuantity'] ?? 0,
                'totalQuantity' => $inventory->total_quantity,
                'category' => $inventory->category?->category_name,
                'status' => $statusKey,
                'statusLabel' => $availability['statusLabel'] ?? InventoryAvailabilityService::statusLabel($statusKey),
                'futureAvailable' => $futureAvailable,
                'futureReturns' => $futureReturns,
                'hasFutureReturns' => $futureReturns > 0,
            ];
        }

        $perPage = 10;
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        return response()->json([
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'pagination' => [
                'currentPage' => $page,
                'lastPage' => $lastPage,
                'perPage' => $perPage,
                'total' => $total,
                'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $total),
            ],
        ]);
    }
}
