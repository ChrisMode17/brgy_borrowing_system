<x-layouts.app :title="'Dashboard'">
    <div
        class="space-y-6"
        x-data="{
            transactionsOpen: false,
            transactionsLoading: false,
            transactionsError: '',
            async loadTransactions(url = '{{ route('dashboard.transactions') }}') {
                this.transactionsLoading = true;
                this.transactionsError = '';

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'text/html',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error('Unable to load transactions.');
                    }

                    this.$refs.transactionResults.innerHTML = await response.text();
                } catch {
                    this.transactionsError = 'Transactions could not be loaded. Please try again.';
                } finally {
                    this.transactionsLoading = false;
                }
            },
            async submitTransactionFilters(event) {
                const url = new URL(event.target.action, window.location.origin);
                new FormData(event.target).forEach((value, key) => {
                    if (value !== '') {
                        url.searchParams.set(key, value);
                    } else {
                        url.searchParams.delete(key);
                    }
                });
                url.searchParams.delete('page');
                await this.loadTransactions(url);
            },
            async changeTransactionPage(event) {
                const link = event.target.closest('a[href]');
                if (link && this.$refs.transactionResults.contains(link)) {
                    event.preventDefault();
                    await this.loadTransactions(link.href);
                }
            },
        }"
        @keydown.escape.window="transactionsOpen = false"
    >
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Welcome back, {{ auth()->user()->first_name }} 👋</h2>
            <p class="text-slate-500 text-sm">Here's what's happening with your equipment inventory today.</p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            @php
                $cards = [
                    ['label' => 'Equipment Types', 'value' => $totalEquipment, 'icon' => '📦', 'color' => 'bg-blue-50 text-blue-700'],
                    ['label' => 'Total Units', 'value' => $totalUnits, 'icon' => '🔢', 'color' => 'bg-indigo-50 text-indigo-700'],
                    ['label' => 'Active Borrows', 'value' => $activeBorrows, 'icon' => '📤', 'color' => 'bg-amber-50 text-amber-700'],
                    ['label' => 'Pending Reservations', 'value' => $pendingReservations, 'icon' => '📅', 'color' => 'bg-purple-50 text-purple-700'],
                    ['label' => 'Late Returns', 'value' => $lateReturns, 'icon' => '⏰', 'color' => 'bg-rose-50 text-rose-700'],
                ];
            @endphp
            @foreach ($cards as $card)
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="h-9 w-9 rounded-lg flex items-center justify-center text-lg {{ $card['color'] }}">{{ $card['icon'] }}</div>
                    <p class="text-2xl font-bold text-slate-900 mt-3">{{ $card['value'] }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $card['label'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Recent Transactions</h3>
                    <button
                        type="button"
                        class="text-sm text-brand-600 hover:underline"
                        @click="transactionsOpen = true; loadTransactions()"
                    >View all</button>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr>
                            <th class="text-left px-5 py-2">Ref No.</th>
                            <th class="text-left px-5 py-2">Borrower</th>
                            <th class="text-left px-5 py-2">Return Date</th>
                            <th class="text-left px-5 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentTransactions as $t)
                            <tr>
                                <td class="px-5 py-3 font-medium text-slate-800">{{ $t->refnum }}</td>
                                <td class="px-5 py-3">{{ $t->borrower?->first_name }} {{ $t->borrower?->last_name }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ optional($t->returnDate)->format('M d, Y h:i A') }}</td>
                                <td class="px-5 py-3"><x-status-badge :status="$t->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-slate-400">No transactions yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="font-semibold text-slate-800 mb-4">By Category</h3>
                <div class="space-y-3">
                    @forelse ($categoryBreakdown as $cat)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-slate-700">{{ $cat->category_name }}</span>
                                <span class="text-slate-500">{{ $cat->item_count }} items / {{ $cat->unit_count }} units</span>
                            </div>
                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-brand-500" style="width: {{ $totalUnits > 0 ? min(100, round($cat->unit_count / $totalUnits * 100)) : 0 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">No inventory yet</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div
            x-cloak
            x-show="transactionsOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-3 sm:p-6"
            role="presentation"
            @click.self="transactionsOpen = false"
        >
            <section
                class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="transactions-dialog-title"
                @click="changeTransactionPage($event)"
            >
                <header class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="transactions-dialog-title" class="text-lg font-semibold text-slate-900">All Transactions</h2>
                        <p class="mt-1 text-sm text-slate-500">Search and filter borrowing activity.</p>
                    </div>
                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-2xl leading-none text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                        aria-label="Close transactions"
                        @click="transactionsOpen = false"
                    >&times;</button>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                    <div x-show="transactionsError" x-text="transactionsError" role="alert" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>
                    <div x-show="transactionsLoading" class="mb-4 text-sm text-slate-500" role="status">Loading transactions...</div>
                    <div x-ref="transactionResults" @submit.prevent="submitTransactionFilters($event)"></div>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
