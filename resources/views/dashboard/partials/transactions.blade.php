<div class="space-y-4">
    <form method="GET" action="{{ route('dashboard.transactions') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <label class="sm:col-span-2 lg:col-span-2">
            <span class="mb-1 block text-xs font-semibold text-slate-600">Search</span>
            <input
                type="search"
                name="search"
                value="{{ $filters['search'] }}"
                placeholder="Reference number or borrower"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
            >
        </label>
        <label>
            <span class="mb-1 block text-xs font-semibold text-slate-600">Status</span>
            <select name="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                <option value="">All statuses</option>
                @foreach (['pending' => 'Pending', 'borrowed' => 'Borrowed', 'late' => 'Late', 'returned' => 'Returned'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="mb-1 block text-xs font-semibold text-slate-600">Borrowed from</span>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
        </label>
        <label>
            <span class="mb-1 block text-xs font-semibold text-slate-600">Borrowed to</span>
            <input type="date" name="to" value="{{ $filters['to'] }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
        </label>
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">Apply filters</button>
            <a href="{{ route('dashboard.transactions') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Clear</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Ref No.</th>
                    <th class="px-4 py-3">Borrower</th>
                    <th class="px-4 py-3">Borrow Date</th>
                    <th class="px-4 py-3">Return Date</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($transactions as $transaction)
                    <tr class="hover:bg-slate-50/70">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-800">{{ $transaction->refnum }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $transaction->borrower?->first_name }} {{ $transaction->borrower?->last_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ optional($transaction->dateBorrowed)->format('M d, Y h:i A') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ optional($transaction->returnDate)->format('M d, Y h:i A') }}</td>
                        <td class="whitespace-nowrap px-4 py-3"><x-status-badge :status="$transaction->status" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">No transactions match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">
            @if ($transactions->total() > 0)
                Showing {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} of {{ $transactions->total() }} transactions
            @else
                No transactions to display
            @endif
        </p>
        {{ $transactions->links() }}
    </div>
</div>
