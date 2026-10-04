<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['all', 'borrowed', 'late'])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? 'all';

        $query = Transaction::with(['borrower', 'items.inventory'])
            ->orderByDesc('created_at');

        if ($status === 'all') {
            $query->whereIn('status', ['borrowed', 'late']);
        } else {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('refnum', 'like', "%{$search}%")
                    ->orWhereHas('borrower', function (Builder $borrowerQuery) use ($search): void {
                        $borrowerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $transactions = $query->paginate(10)->withQueryString();

        if ($request->header('X-Live-Filter') === 'true') {
            return view('return.partials.results', compact('transactions'));
        }

        return view('return.index', compact('transactions', 'search', 'status'));
    }

    public function returnAll(string $refnum)
    {
        $result = TransactionService::returnAll($refnum);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function partialReturn(Request $request, string $refnum)
    {
        $validated = $request->validate([
            'returns' => ['required', 'array'],
        ]);

        $result = TransactionService::partialReturn($refnum, $validated['returns']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
