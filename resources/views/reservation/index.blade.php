<x-layouts.app :title="'Reservations'">
    <div class="space-y-5" x-data="reservationPage(@js($search), @js($status))" x-init="load()">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Reservations</h2>
                <p class="text-slate-500 text-sm">Pending reservations become "Borrowed" once confirmed on/after the scheduled date.</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3 items-center">
            <input type="search" x-model="search" @input.debounce.400ms="load()" placeholder="Search ref no. or borrower..."
                   class="flex-1 min-w-[200px] rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            <select x-model="status" @change="load()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="all">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="borrowed">Borrowed</option>
                <option value="late">Late</option>
                <option value="returned">Returned</option>
            </select>
        </div>

        <div x-show="error" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" x-text="error"></div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Ref No.</th>
                        <th class="text-left px-5 py-3">Borrower</th>
                        <th class="text-left px-5 py-3">Items</th>
                        <th class="text-left px-5 py-3">Borrow Date</th>
                        <th class="text-left px-5 py-3">Return Date</th>
                        <th class="text-left px-5 py-3">Status</th>
                        <th class="text-right px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-if="loading">
                        <tr><td colspan="7" class="px-5 py-6 text-center text-slate-400">Loading reservations...</td></tr>
                    </template>
                    <template x-if="!loading && !error && items.length === 0">
                        <tr><td colspan="7" class="px-5 py-6 text-center text-slate-400">No reservations found</td></tr>
                    </template>
                    <template x-for="transaction in items" :key="transaction.refnum">
                        <tr>
                            <td class="px-5 py-3 font-medium text-slate-800" x-text="transaction.refnum"></td>
                            <td class="px-5 py-3" x-text="transaction.borrowerName"></td>
                            <td class="px-5 py-3 text-slate-500" x-text="transaction.items"></td>
                            <td class="px-5 py-3 text-slate-500" x-text="transaction.dateBorrowed"></td>
                            <td class="px-5 py-3 text-slate-500" x-text="transaction.returnDate"></td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium"
                                      :class="{
                                          'bg-purple-50 text-purple-700 border-purple-200': transaction.status === 'pending',
                                          'bg-amber-50 text-amber-700 border-amber-200': transaction.status === 'borrowed',
                                          'bg-rose-50 text-rose-700 border-rose-200': transaction.status === 'late',
                                          'bg-slate-100 text-slate-600 border-slate-200': transaction.status === 'returned'
                                      }"
                                      x-text="transaction.status.charAt(0).toUpperCase() + transaction.status.slice(1)"></span>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <template x-if="transaction.status === 'pending'">
                                    <div class="inline-flex items-center gap-2">
                                        <template x-if="transaction.borrowAllowed">
                                            <form method="POST" :action="actionUrl(confirmUrlTemplate, transaction.refnum)" class="inline">
                                                @csrf
                                                <button class="text-emerald-600 hover:underline text-sm">Confirm Borrow</button>
                                            </form>
                                        </template>
                                        <template x-if="!transaction.borrowAllowed">
                                            <button type="button" disabled class="cursor-not-allowed text-slate-400 text-sm" title="Available on/after the scheduled borrow date and time">
                                                Confirm Borrow
                                            </button>
                                        </template>
                                        <form method="POST" :action="actionUrl(cancelUrlTemplate, transaction.refnum)" class="inline ml-2" @submit="return confirm('Cancel this reservation?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-rose-600 hover:underline text-sm">Cancel</button>
                                        </form>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="pagination.lastPage > 1" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-sm text-slate-500" x-text="`Showing ${pagination.from}-${pagination.to} of ${pagination.total} reservations`"></p>
            <div class="flex items-center gap-2">
                <button type="button" @click="load(pagination.currentPage - 1)" :disabled="pagination.currentPage <= 1"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40">Previous</button>
                <span class="px-2 text-sm text-slate-600" x-text="`Page ${pagination.currentPage} of ${pagination.lastPage}`"></span>
                <button type="button" @click="load(pagination.currentPage + 1)" :disabled="pagination.currentPage >= pagination.lastPage"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40">Next</button>
            </div>
        </div>
    </div>

    <script>
        function reservationPage(search, status) {
            return {
                items: [],
                loading: true,
                error: '',
                search,
                status,
                requestId: 0,
                confirmUrlTemplate: @js(route('reservation.confirm', ['refnum' => '__REFNUM__'])),
                cancelUrlTemplate: @js(route('reservation.cancel', ['refnum' => '__REFNUM__'])),
                pagination: { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0 },
                actionUrl(template, refnum) {
                    return template.replace('__REFNUM__', encodeURIComponent(refnum));
                },
                async load(page = 1) {
                    const requestId = ++this.requestId;
                    this.loading = true;
                    this.error = '';

                    const params = new URLSearchParams({ search: this.search, status: this.status, page });

                    try {
                        const response = await fetch(`{{ route('reservation.data') }}?${params}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.message ?? 'Unable to load reservations.');
                        }

                        if (requestId === this.requestId) {
                            this.items = data.items;
                            this.pagination = data.pagination;
                        }
                    } catch (error) {
                        if (requestId === this.requestId) {
                            this.error = error.message ?? 'Unable to load reservations.';
                            this.items = [];
                        }
                    } finally {
                        if (requestId === this.requestId) {
                            this.loading = false;
                        }
                    }
                }
            }
        }
    </script>
</x-layouts.app>
