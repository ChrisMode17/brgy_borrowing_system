<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReservationController extends Controller
{
    private const STATUSES = ['pending', 'borrowed', 'late', 'returned'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in([...self::STATUSES, 'all'])],
        ]);

        return view('reservation.index', [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => $filters['status'] ?? 'pending',
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in([...self::STATUSES, 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? 'pending';

        $query = Transaction::with(['borrower', 'items.inventory'])
            ->orderByDesc('created_at');

        if ($status === 'all') {
            $query->whereIn('status', self::STATUSES);
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

        $transactions = $query->paginate(10, ['*'], 'page', (int) ($filters['page'] ?? 1));
        $now = now(config('app.timezone'));

        return response()->json([
            'items' => $transactions->getCollection()->map(fn (Transaction $transaction): array => [
                'refnum' => $transaction->refnum,
                'borrowerName' => trim(($transaction->borrower?->first_name ?? '').' '.($transaction->borrower?->last_name ?? '')),
                'items' => $transaction->items
                    ->map(fn ($item): string => ($item->inventory?->name ?? 'Unknown').' x'.$item->quantity)
                    ->join(', '),
                'dateBorrowed' => optional($transaction->dateBorrowed)->format('M d, Y h:i A'),
                'returnDate' => optional($transaction->returnDate)->format('M d, Y h:i A'),
                'status' => $transaction->status,
                'borrowAllowed' => $transaction->status === 'pending' && $now->gte($transaction->dateBorrowed),
            ]),
            'pagination' => [
                'currentPage' => $transactions->currentPage(),
                'lastPage' => $transactions->lastPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem() ?? 0,
                'to' => $transactions->lastItem() ?? 0,
            ],
        ]);
    }

    public function confirm(string $refnum)
    {
        $result = TransactionService::confirmBorrow($refnum);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function cancel(string $refnum)
    {
        $transaction = Transaction::findOrFail($refnum);
        $transaction->items()->delete();
        $transaction->delete();

        return back()->with('success', 'Reservation cancelled.');
    }
}
