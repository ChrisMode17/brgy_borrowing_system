<div data-live-filter-results data-live-filter-form="#users-filters">
    <p class="mb-3 text-sm text-slate-500">Showing {{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }} of {{ $users->total() }} users</p>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @forelse ($users as $u)
            @php
                $roleName = $u->role?->role_name ?? 'User';
                $roleBadgeClass = match ($roleName) {
                    'Admin' => 'bg-amber-100 text-amber-700',
                    'Staff' => 'bg-blue-100 text-blue-700',
                    default => 'bg-emerald-100 text-emerald-700',
                };
                $initials = strtoupper(substr($u->first_name, 0, 1) . substr($u->last_name, 0, 1));
                $displayName = $u->first_name . ' ' . $u->last_name;
            @endphp

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sky-100 text-base font-bold text-sky-700">
                            @if ($u->profilePhotoUrl())
                                <img src="{{ $u->profilePhotoUrl() }}" alt="" class="h-full w-full rounded-full object-cover">
                            @else
                                {{ $initials ?: 'U' }}
                            @endif
                        </div>
                        <div class="min-w-0">
                            <h3 class="truncate text-2xl font-bold text-slate-800">{{ $displayName }}</h3>
                            <p class="truncate text-sm text-slate-500">{{ '@' . $u->username }}</p>
                        </div>
                    </div>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $roleBadgeClass }}">{{ $roleName }}</span>
                </div>
                <div class="mt-4 space-y-3 border-t border-slate-100 pt-3 text-sm text-slate-600">
                    <div class="flex items-center gap-3"><span class="text-slate-400">✉</span><span class="truncate">{{ $u->email }}</span></div>
                    <div class="flex items-center gap-3"><span class="text-slate-400">☎</span><span>{{ $u->contact_number }}</span></div>
                    <div class="flex items-center gap-3"><span class="text-slate-400">🆔</span><span>{{ $u->access_level }}</span></div>
                </div>
                <div class="mt-4 flex gap-2">
                    @if (auth()->user()->canManage($u->access_level))
                        <button @click='editing = @json($u)' class="flex-1 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">Edit</button>
                        @if ($u->user_id !== auth()->id())
                            <form method="POST" action="{{ route('users.destroy', $u->user_id) }}" class="flex-1" onsubmit="return confirm('Delete this user?')">
                                @csrf @method('DELETE')
                                <button class="w-full rounded-lg bg-red-500 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-600">Delete</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                <p class="font-medium text-slate-700">No users found</p>
                <p class="mt-1 text-sm text-slate-500">Try adjusting the search or access level filter.</p>
            </div>
        @endforelse
    </div>

    {{ $users->onEachSide(1)->links() }}
</div>
