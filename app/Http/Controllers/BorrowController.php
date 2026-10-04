<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\ReturnIssue;
use App\Services\ReferenceNumberGenerator;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon as SupportCarbon;

class BorrowController extends Controller
{
    public function create()
    {
        $refnum = ReferenceNumberGenerator::generate();
        $categories = InventoryCategory::orderBy('category_name')->get();

        return view('borrow.create', compact('refnum', 'categories'));
    }

    public function returnIssues(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fname' => ['required', 'string', 'max:50'],
            'lname' => ['required', 'string', 'max:50'],
        ]);

        $firstName = strtolower(trim($validated['fname']));
        $lastName = strtolower(trim($validated['lname']));

        if ($firstName === '' || $lastName === '') {
            return response()->json(['issues' => []]);
        }

        $issues = ReturnIssue::query()
            ->with(['borrowItem.inventory', 'borrowItem.transaction'])
            ->whereHas('borrowItem.transaction.borrower', function (Builder $query) use ($firstName, $lastName): void {
                $query->whereRaw('LOWER(first_name) = ?', [$firstName])
                    ->whereRaw('LOWER(last_name) = ?', [$lastName]);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'issue_page')
            ->through(fn (ReturnIssue $issue): array => [
                'equipment' => $issue->borrowItem?->inventory?->name ?? 'Unknown equipment',
                'reference' => $issue->borrowItem?->transaction?->refnum,
                'brokenQuantity' => (int) $issue->broken_qty,
                'maintenanceQuantity' => (int) $issue->maintenance_qty,
                'reportedAt' => SupportCarbon::parse($issue->created_at)->format('M j, Y'),
            ]);

        return response()->json([
            'issues' => $issues->items(),
            'pagination' => [
                'currentPage' => $issues->currentPage(),
                'lastPage' => $issues->lastPage(),
                'perPage' => $issues->perPage(),
                'total' => $issues->total(),
                'from' => $issues->firstItem(),
                'to' => $issues->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'refnum' => ['nullable', 'string'],
            'fname' => ['required', 'string', 'max:50'],
            'lname' => ['required', 'string', 'max:50'],
            'acknowledged_return_issues' => ['nullable', 'boolean'],
            'addr' => ['required', 'string', 'max:100'],
            'contNum' => ['required', 'string', 'max:255'],
            'dateBorrowed' => ['required', 'date'],
            'returnDate' => ['required', 'date'],
            'borrowItems' => ['required', 'array', 'min:1'],
            'borrowQuantities' => ['required', 'array', 'min:1'],
        ]);

        $manila = 'Asia/Manila';
        $borrowDateTime = Carbon::parse($validated['dateBorrowed'], $manila);
        $returnDateTime = Carbon::parse($validated['returnDate'], $manila);
        $now = Carbon::now($manila);
        $minBorrowDate = $now->copy()->addDays(3);

        if ($borrowDateTime->lt($minBorrowDate)) {
            return back()->with('error', 'Borrow date must be at least 3 days in advance and cannot be in the past.')->withInput();
        }
        if ($returnDateTime->lt($now)) {
            return back()->with('error', 'Return date cannot be in the past.')->withInput();
        }
        if ($returnDateTime->lte($borrowDateTime)) {
            return back()->with('error', 'Return date must be after the borrow date.')->withInput();
        }

        $hasReturnIssues = ReturnIssue::query()
            ->whereHas('borrowItem.transaction.borrower', function (Builder $query) use ($validated): void {
                $query->whereRaw('LOWER(first_name) = ?', [strtolower(trim($validated['fname']))])
                    ->whereRaw('LOWER(last_name) = ?', [strtolower(trim($validated['lname']))]);
            })
            ->exists();

        if ($hasReturnIssues && ! $request->boolean('acknowledged_return_issues')) {
            return back()
                ->with('error', 'Review the borrower’s return issues and confirm before submitting this reservation.')
                ->withInput();
        }

        $items = [];
        foreach ($validated['borrowItems'] as $i => $equipmentId) {
            $items[] = ['equipment_id' => (int) $equipmentId, 'quantity' => (int) ($validated['borrowQuantities'][$i] ?? 0)];
        }

        try {
            $result = TransactionService::reserve(
                $validated['fname'],
                $validated['lname'],
                $validated['addr'],
                $validated['contNum'],
                $borrowDateTime->format('Y-m-d H:i:00'),
                $returnDateTime->format('Y-m-d H:i:00'),
                $items
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        if (! $result['success']) {
            return back()->with('error', $result['message'])->withInput();
        }

        return redirect()->route('reservation.index')->with('success', $result['message']);
    }
}
