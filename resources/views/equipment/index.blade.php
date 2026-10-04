<x-layouts.app :title="'Manage Equipment'">
    <div class="space-y-5" x-data="{ showAdd: false, showBulk: @json($errors->has('file')), editing: null, fixing: null }">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Manage Equipment</h2>
                <p class="text-slate-500 text-sm">Add, edit, and track equipment inventory and maintenance.</p>
            </div>
            <div class="flex gap-2">
                <button @click="showBulk = true" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium">Bulk Add (CSV/Excel)</button>
                <button @click="showAdd = true" class="rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-medium">+ Add Equipment</button>
            </div>
        </div>

        <form id="equipment-filters" method="GET" action="{{ route('equipment.index') }}" data-live-filter-form data-live-filter-target="[data-live-filter-results]" data-live-filter-error="#equipment-filter-error" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_180px_200px]">
            <div>
                <label for="equipment-search" class="sr-only">Search equipment</label>
                <input id="equipment-search" type="search" name="search" value="{{ $filters['search'] }}" data-live-filter-search placeholder="Search equipment name..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
            </div>
            <div>
                <label for="equipment-category-filter" class="sr-only">Filter by category</label>
                <select id="equipment-category-filter" name="category" data-live-filter-control class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="equipment-status-filter" class="sr-only">Filter by status</label>
                <select id="equipment-status-filter" name="status" data-live-filter-control class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    <option value="">All statuses</option>
                    <option value="available" @selected($filters['status'] === 'available')>Available</option>
                    <option value="under_maintenance" @selected($filters['status'] === 'under_maintenance')>Under Maintenance</option>
                    <option value="broken" @selected($filters['status'] === 'broken')>Broken</option>
                    <option value="lost" @selected($filters['status'] === 'lost')>Lost</option>
                    <option value="borrowed" @selected($filters['status'] === 'borrowed')>Borrowed</option>
                </select>
            </div>
        </form>
        <p id="equipment-filter-error" class="hidden rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"></p>

        @include('equipment.partials.results')

        <!-- Add modal -->
        <div x-show="showAdd" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showAdd = false">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                <h3 class="font-semibold text-lg">Add Equipment</h3>
                <form method="POST" action="{{ route('equipment.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                        <input type="text" name="name" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <select name="category" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @foreach ($categories as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Total Quantity</label>
                        <input type="number" name="totalQuantity" min="1" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showAdd = false" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-brand-600 text-white">Add</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk add modal -->
        <div x-show="showBulk" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showBulk = false">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                <h3 class="font-semibold text-lg">Bulk Add Equipment (CSV or Excel)</h3>
                <p class="text-sm text-slate-500">Upload a CSV or .xlsx file with the columns name, category, totalQuantity. Category must be Equipment, Tool, or Unit. Replace or remove the sample rows before uploading.</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('equipment.bulk-template.csv') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Download CSV template</a>
                    <a href="{{ route('equipment.bulk-template.xlsx') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Download Excel template</a>
                </div>
                <form method="POST" action="{{ route('equipment.bulk-store') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="file" name="file" accept=".csv,.xlsx" required class="w-full text-sm">
                    @error('file')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showBulk = false" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-brand-600 text-white">Upload</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit modal -->
        <div x-show="editing" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="editing = null">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4" x-show="editing">
                <h3 class="font-semibold text-lg">Edit Equipment</h3>
                <form :action="editing ? `/equipment/${editing.equipment_id}` : ''" method="POST" class="space-y-3">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                        <input type="text" name="name" :value="editing?.name" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <select name="category" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @foreach ($categories as $c)
                                <option value="{{ $c }}" x-bind:selected="editing?.category_name === '{{ $c }}'">{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Total Quantity</label>
                        <input type="number" name="totalQuantity" min="1" :value="editing?.total_quantity" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Available Quantity</label>
                        <input type="number" name="availableQuantity" min="0" :value="editing?.snapshot?.availableQuantity ?? 0" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="editing = null" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-brand-600 text-white">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Fix maintenance modal -->
        <div x-show="fixing" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="fixing = null">
            <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6 space-y-4" x-show="fixing">
                <h3 class="font-semibold text-lg">Mark as Fixed</h3>
                <p class="text-sm text-slate-500" x-text="fixing ? `${fixing.name} — up to ${fixing.max} unit(s) under maintenance` : ''"></p>
                <form :action="fixing ? `/equipment/${fixing.id}/fix` : ''" method="POST" class="space-y-3">
                    @csrf
                    <input type="number" name="quantity" min="1" :max="fixing?.max" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="fixing = null" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-brand-600 text-white">Confirm Fixed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
