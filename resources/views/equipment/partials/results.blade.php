<div data-live-filter-results data-live-filter-form="#equipment-filters">
    <p class="mb-3 text-sm text-slate-500">Showing {{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }} of {{ $items->total() }} equipment items</p>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-5 py-3">Name</th>
                    <th class="text-left px-5 py-3">Category</th>
                    <th class="text-left px-5 py-3">Available</th>
                    <th class="text-left px-5 py-3">Total</th>
                    <th class="text-left px-5 py-3">Under Maintenance</th>
                    <th class="text-left px-5 py-3">Status</th>
                    <th class="text-right px-5 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-800">{{ $item->name }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $item->category_name }}</td>
                        <td class="px-5 py-3">{{ $item->snapshot['availableQuantity'] ?? 0 }}</td>
                        <td class="px-5 py-3">{{ $item->total_quantity }}</td>
                        <td class="px-5 py-3">{{ $item->snapshot['maintenanceQuantity'] ?? 0 }}</td>
                        <td class="px-5 py-3"><x-status-badge :status="$item->snapshot['statusKey'] ?? 'available'" /></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            @if (auth()->user()->canEditEquipment())
                                <button @click='editing = @json($item)' class="text-brand-600 hover:underline text-sm">Edit</button>
                                @if (($item->snapshot['maintenanceQuantity'] ?? 0) > 0)
                                    <button @click="fixing = { id: {{ $item->equipment_id }}, name: @js($item->name), max: {{ $item->snapshot['maintenanceQuantity'] ?? 0 }} }" class="ml-2 text-amber-600 hover:underline text-sm">Fix</button>
                                @endif
                            @endif
                            @if (auth()->user()->canDeleteEquipment())
                                <form method="POST" action="{{ route('equipment.destroy', $item->equipment_id) }}" class="inline ml-2" onsubmit="return confirm('Delete this equipment?')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 hover:underline text-sm">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-6 text-center text-slate-400">No equipment matches your search or filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $items->onEachSide(1)->links() }}
</div>
