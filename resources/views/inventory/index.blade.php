<x-layouts.app :title="'Inventory'">
    <div class="space-y-5" x-data="inventoryPage()" x-init="load()">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Inventory</h2>
                <p class="text-slate-500 text-sm">Live availability across all equipment, tools, and units.</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3 items-center">
            <input type="text" x-model="search" @input.debounce.400ms="load()" placeholder="Search equipment..."
                   class="flex-1 min-w-[200px] rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            <select x-model="category" @change="load()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All Categories</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->category_name }}">{{ $c->category_name }}</option>
                @endforeach
            </select>
            <select x-model="status" @change="load()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All Statuses</option>
                <option value="available">Available</option>
                <option value="borrowed">Borrowed</option>
                <option value="undermaintenance">Under Maintained</option>
                <option value="broken">Broken</option>
                <option value="lost">Lost</option>
            </select>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Name</th>
                        <th class="text-left px-5 py-3">Category</th>
                        <th class="text-left px-5 py-3">Available</th>
                        <th class="text-left px-5 py-3">Total</th>
                        <th class="text-left px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-if="loading">
                        <tr><td colspan="5" class="px-5 py-6 text-center text-slate-400">Loading...</td></tr>
                    </template>
                    <template x-if="!loading && items.length === 0">
                        <tr><td colspan="5" class="px-5 py-6 text-center text-slate-400">No equipment found</td></tr>
                    </template>
                    <template x-for="item in items" :key="item.id">
                        <tr>
                            <td class="px-5 py-3 font-medium text-slate-800" x-text="item.name"></td>
                            <td class="px-5 py-3 text-slate-500" x-text="item.category"></td>
                            <td class="px-5 py-3" x-text="item.availableQuantity"></td>
                            <td class="px-5 py-3" x-text="item.totalQuantity"></td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border"
                                      :class="{
                                        'bg-emerald-50 text-emerald-700 border-emerald-200': item.status === 'available',
                                        'bg-amber-50 text-amber-700 border-amber-200': item.status === 'borrowed',
                                        'bg-orange-50 text-orange-700 border-orange-200': item.status === 'under_maintenance',
                                        'bg-rose-50 text-rose-700 border-rose-200': item.status === 'broken',
                                        'bg-slate-100 text-slate-500 border-slate-200': item.status === 'lost',
                                      }" x-text="item.statusLabel"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="pagination.lastPage > 1" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-sm text-slate-500" x-text="`Showing ${pagination.from}-${pagination.to} of ${pagination.total}`"></p>
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
        function inventoryPage() {
            return {
                items: [], loading: true, search: '', category: '', status: '',
                pagination: { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0 },
                async load(page = 1) {
                    this.loading = true;
                    const params = new URLSearchParams({ search: this.search, category: this.category, status: this.status, page });
                    const res = await fetch(`{{ route('inventory.data') }}?${params}`);
                    const data = await res.json();
                    this.items = data.items ?? [];
                    this.pagination = data.pagination ?? this.pagination;
                    this.loading = false;
                }
            }
        }
    </script>
</x-layouts.app>
