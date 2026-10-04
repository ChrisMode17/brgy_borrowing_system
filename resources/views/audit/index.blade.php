<x-layouts.app :title="'Audit Trail'">
    <div class="space-y-5">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Audit Trail</h2>
                <p class="text-slate-500 text-sm">Every login, edit, borrow, and system action, in order.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('audit.export', array_merge(request()->query(), ['format' => 'csv'])) }}" data-live-filter-export data-live-filter-form="#audit-filters" data-no-navigation class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium">Export CSV</a>
                <a href="{{ route('audit.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" data-live-filter-export data-live-filter-form="#audit-filters" data-no-navigation class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium">Export Excel</a>
                <a href="{{ route('audit.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" data-live-filter-export data-live-filter-form="#audit-filters" data-no-navigation class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium">Export PDF</a>
            </div>
        </div>

        <form id="audit-filters" method="GET" action="{{ route('audit.index') }}" data-live-filter-form data-live-filter-target="[data-live-filter-results]" data-live-filter-error="#audit-filter-error" class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3">
            <input type="search" name="search" value="{{ $search }}" data-live-filter-search placeholder="Search..." class="flex-1 min-w-[200px] rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <select name="typeFilter" data-live-filter-control class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All Types</option>
                @foreach (['System', 'Equipment', 'User'] as $type)
                    <option value="{{ $type }}" @selected($typeFilter === $type)>{{ $type }}</option>
                @endforeach
            </select>
            <select name="dateFilter" data-live-filter-control class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All Time</option>
                <option value="1hour" @selected($dateFilter === '1hour')>Last 1 hour</option>
                <option value="1day" @selected($dateFilter === '1day')>Last 1 day</option>
                <option value="1week" @selected($dateFilter === '1week')>Last 1 week</option>
            </select>
        </form>
        <p id="audit-filter-error" class="hidden rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"></p>

        @include('audit.partials.results')
    </div>
</x-layouts.app>
