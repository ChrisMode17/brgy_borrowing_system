<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        TransactionService::updateLateTransactions();
        TransactionService::deleteOldPendingTransactions();

        $totalEquipment = Inventory::count();
        $totalUnits = (int) Inventory::sum('total_quantity');
        $activeBorrows = Transaction::whereIn('status', ['borrowed', 'late'])->count();
        $pendingReservations = Transaction::where('status', 'pending')->count();
        $lateReturns = Transaction::where('status', 'late')->count();

        $recentTransactions = Transaction::with('borrower')->orderByDesc('created_at')->limit(8)->get();

        $categoryBreakdown = DB::table('inventory')
            ->join('inventory_categories', 'inventory.category_id', '=', 'inventory_categories.category_id')
            ->select('inventory_categories.category_name', DB::raw('COUNT(*) as item_count'), DB::raw('SUM(inventory.total_quantity) as unit_count'))
            ->groupBy('inventory_categories.category_name')
            ->get();

        return view('dashboard.index', compact(
            'totalEquipment', 'totalUnits', 'activeBorrows', 'pendingReservations',
            'lateReturns', 'recentTransactions', 'categoryBreakdown'
        ));
    }

    public function transactions(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,borrowed,late,returned'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $search = trim($filters['search'] ?? '');

        $transactions = Transaction::with('borrower')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('refnum', 'like', "%{$search}%")
                        ->orWhereHas('borrower', function ($borrowers) use ($search): void {
                            $borrowers->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('dateBorrowed', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('dateBorrowed', '<=', $date))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.partials.transactions', [
            'transactions' => $transactions,
            'filters' => [
                'search' => $search,
                'status' => $filters['status'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }
}
