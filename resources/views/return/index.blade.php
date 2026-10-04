<x-layouts.app :title="'Returns'">
    <div class="space-y-5" x-data="{ activeModal: null }">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Returns</h2>
                <p class="text-slate-500 text-sm">Process full or partial returns and flag broken / under-maintenance units.</p>
            </div>
        </div>

        <form id="return-filters" method="GET" action="{{ route('return.index') }}" data-live-filter-form data-live-filter-target="[data-live-filter-results]" data-live-filter-error="#return-filter-error" class="flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4">
            <div>
                <label for="return-search" class="sr-only">Search returns</label>
                <input id="return-search" type="search" name="search" value="{{ $search }}" data-live-filter-search placeholder="Search ref no. or borrower..."
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 sm:w-80">
            </div>
            <div>
                <label for="return-status" class="sr-only">Filter returns by status</label>
                <select id="return-status" name="status" data-live-filter-control class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                    <option value="all" @selected($status === 'all')>All Active Returns</option>
                    <option value="borrowed" @selected($status === 'borrowed')>Borrowed</option>
                    <option value="late" @selected($status === 'late')>Late</option>
                </select>
            </div>
        </form>
        <p id="return-filter-error" class="hidden rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"></p>

        @include('return.partials.results')
    </div>
</x-layouts.app>
