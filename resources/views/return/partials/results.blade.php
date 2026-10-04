<div class="space-y-3" data-live-filter-results data-live-filter-form="#return-filters">
    <p class="text-sm text-slate-500">Showing {{ $transactions->firstItem() ?? 0 }}-{{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }} active borrows</p>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-5 py-3">Ref No.</th>
                    <th class="text-left px-5 py-3">Borrower</th>
                    <th class="text-left px-5 py-3">Items</th>
                    <th class="text-left px-5 py-3">Due</th>
                    <th class="text-left px-5 py-3">Status</th>
                    <th class="text-right px-5 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($transactions as $t)
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-800">{{ $t->refnum }}</td>
                        <td class="px-5 py-3">{{ $t->borrower?->first_name }} {{ $t->borrower?->last_name }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $t->items->where('quantity', '>', 0)->map(fn($i) => ($i->inventory?->name ?? 'Unknown') . ' x' . $i->quantity)->join(', ') }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ optional($t->returnDate)->format('M d, Y h:i A') }}</td>
                        <td class="px-5 py-3"><x-status-badge :status="$t->status" /></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('return.all', $t->refnum) }}" class="inline" onsubmit="return confirm('Mark all items as returned in good condition?')">
                                @csrf
                                <button class="text-emerald-600 hover:underline text-sm">Return All (Good)</button>
                            </form>
                            <button @click="activeModal = @js($t->refnum)" class="ml-2 text-brand-600 hover:underline text-sm">Partial Return</button>
                        </td>
                    </tr>
                    <template x-if="activeModal === @js($t->refnum)">
                        <tr>
                            <td colspan="6" class="p-0">
                                <div class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="activeModal = null">
                                    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full p-6 space-y-4">
                                        <h3 class="font-semibold text-lg">Partial Return — {{ $t->refnum }}</h3>
                                        <form method="POST" action="{{ route('return.partial', $t->refnum) }}" class="space-y-3">
                                            @csrf
                                            <table class="w-full text-sm">
                                                <thead class="text-xs text-slate-500 uppercase">
                                                    <tr>
                                                        <th class="text-left py-1">Item</th>
                                                        <th class="text-left py-1">Borrowed</th>
                                                        <th class="text-left py-1">Good</th>
                                                        <th class="text-left py-1">Maintenance</th>
                                                        <th class="text-left py-1">Broken</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($t->items->where('quantity', '>', 0) as $item)
                                                        <tr>
                                                            <td class="py-1">{{ $item->inventory?->name ?? 'Unknown' }}</td>
                                                            <td class="py-1">{{ $item->quantity }}</td>
                                                            <td class="py-1"><input type="number" min="0" max="{{ $item->quantity }}" value="0" name="returns[{{ $item->id }}][normal]" class="w-16 rounded border border-slate-300 px-2 py-1"></td>
                                                            <td class="py-1"><input type="number" min="0" max="{{ $item->quantity }}" value="0" name="returns[{{ $item->id }}][maintenance]" class="w-16 rounded border border-slate-300 px-2 py-1"></td>
                                                            <td class="py-1"><input type="number" min="0" max="{{ $item->quantity }}" value="0" name="returns[{{ $item->id }}][broken]" class="w-16 rounded border border-slate-300 px-2 py-1"></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            <div class="flex justify-end gap-2 pt-2">
                                                <button type="button" @click="activeModal = null" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
                                                <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-brand-600 text-white">Submit Return</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                @empty
                    <tr><td colspan="6" class="px-5 py-6 text-center text-slate-400">No active borrows found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $transactions->links() }}
</div>
