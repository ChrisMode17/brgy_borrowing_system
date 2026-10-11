<x-layouts.app :title="'Manage Users'">
    <div class="space-y-5" x-data="{ showAdd: false, editing: null, promoting: null }">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Manage Users</h2>
                <p class="text-slate-500 text-sm">You can only manage accounts with a lower privilege level than your own.</p>
            </div>
            <button @click="showAdd = true" class="rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-medium">+ Add User</button>
        </div>

        <form id="users-filters" method="GET" action="{{ route('users.index') }}" data-live-filter-form data-live-filter-target="[data-live-filter-results]" data-live-filter-error="#users-filter-error" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_190px]">
            <div>
                <label for="user-search" class="sr-only">Search users</label>
                <input id="user-search" type="search" name="search" value="{{ $filters['search'] }}" data-live-filter-search placeholder="Search name, username, email, or contact..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
            </div>
            <div>
                <label for="user-access-filter" class="sr-only">Filter by access level</label>
                <select id="user-access-filter" name="access_level" data-live-filter-control class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    <option value="">All access levels</option>
                    <option value="0" @selected((string) $filters['access_level'] === '0')>0 - Admin</option>
                    <option value="1" @selected((string) $filters['access_level'] === '1')>1 - Semi-Admin</option>
                    <option value="2" @selected((string) $filters['access_level'] === '2')>2 - Moderator</option>
                    <option value="3" @selected((string) $filters['access_level'] === '3')>3 - User</option>
                </select>
            </div>
        </form>
        <p id="users-filter-error" class="hidden rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"></p>

        @include('users.partials.results')

        <!-- Admin transfer confirmation modal -->
        <div x-show="promoting" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="promoting = null">
            <div class="w-full max-w-md space-y-4 rounded-xl bg-white p-6 shadow-xl" x-show="promoting">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Transfer Admin access?</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        This will make <strong x-text="promoting?.first_name + ' ' + promoting?.last_name"></strong> the Admin.
                        Your account will become a Semi-Admin and will no longer be the Admin.
                    </p>
                </div>
                <form :action="promoting ? `/users/${promoting.user_id}/promote` : ''" method="POST">
                    @csrf
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="promoting = null" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Cancel</button>
                        <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Yes, transfer Admin</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add modal -->
        <div x-show="showAdd" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showAdd = false">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-3 max-h-[90vh] overflow-y-auto">
                <h3 class="font-semibold text-lg">Add User</h3>
                <form method="POST" action="{{ route('users.store') }}" class="space-y-3">
                    @csrf
                    <input type="text" name="username" placeholder="Username" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="firstName" placeholder="First name" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <input type="text" name="lastName" placeholder="Last name" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <input type="email" name="email" placeholder="Email" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <input type="password" name="password" placeholder="Password (min 6 chars)" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <input type="text" name="contactNumber" placeholder="Contact number" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <select name="accessLevel" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @if (auth()->user()->access_level === 0)
                            <option value="0">0 - Admin</option>
                        @endif
                        @if (auth()->user()->access_level <= 1)
                            <option value="1">1 - Semi-Admin</option>
                        @endif
                        @if (auth()->user()->access_level <= 2)
                            <option value="2">2 - Moderator</option>
                        @endif
                        <option value="3" selected>3 - User</option>
                    </select>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showAdd = false" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-brand-600 text-white">Add</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit modal -->
        <div x-show="editing" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="editing = null">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-3 max-h-[90vh] overflow-y-auto" x-show="editing">
                <h3 class="font-semibold text-lg">Edit User</h3>
                <form :action="editing ? `/users/${editing.user_id}` : ''" method="POST" class="space-y-3">
                    @csrf @method('PUT')
                    <input type="text" name="username" :value="editing?.username" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="firstName" :value="editing?.first_name" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <input type="text" name="lastName" :value="editing?.last_name" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <input type="email" name="email" :value="editing?.email" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <input type="text" name="contactNumber" :value="editing?.contact_number" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @if (auth()->user()->access_level <= 1)
                        <select name="accessLevel" :value="editing?.access_level" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @if (auth()->user()->access_level === 0)
                                <option value="0">0 - Admin</option>
                            @endif
                            @if (auth()->user()->access_level <= 1)
                                <option value="1">1 - Semi-Admin</option>
                            @endif
                            <option value="2">2 - Moderator</option>
                            <option value="3">3 - User</option>
                        </select>
                    @else
                        <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                            Access level: <span x-text="editing?.access_level"></span>
                        </p>
                    @endif
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="editing = null" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-brand-600 text-white">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
