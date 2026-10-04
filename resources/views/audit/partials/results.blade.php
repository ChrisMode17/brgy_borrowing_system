<div data-live-filter-results data-live-filter-form="#audit-filters">
    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-5 py-3">#</th>
                    <th class="text-left px-5 py-3">Actor</th>
                    <th class="text-left px-5 py-3">Role</th>
                    <th class="text-left px-5 py-3">Action</th>
                    <th class="text-left px-5 py-3">Type</th>
                    <th class="text-left px-5 py-3">Details</th>
                    <th class="text-left px-5 py-3">Date/Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-5 py-3 text-slate-400">{{ $seqMap[$log->id] ?? $log->id }}</td>
                        <td class="px-5 py-3 font-medium text-slate-800">{{ $log->actor }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $log->role }}</td>
                        <td class="px-5 py-3">{{ $log->action }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $log->type }}</td>
                        <td class="px-5 py-3 text-slate-500 max-w-md truncate" title="{{ $log->information }}">{{ $log->information }}</td>
                        <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ optional($log->datetime)->format('M d, Y h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-6 text-center text-slate-400">No audit entries found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
