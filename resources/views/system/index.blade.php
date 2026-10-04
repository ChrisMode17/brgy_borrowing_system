<x-layouts.app :title="'System Settings'">
    <div class="max-w-3xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">System Settings</h2>
            <p class="text-slate-500 text-sm">Manage system identity, login background, and backups.</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h3 class="font-semibold text-slate-800">System Backup</h3>
            <p class="mt-1 text-sm text-slate-500">Download database records and uploaded profile photos, system icons, and login backgrounds in one ZIP file. Backups contain account password hashes and other system data; store them securely.</p>
            <a href="{{ route('system.backup') }}" class="mt-4 inline-flex rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Download Full Backup</a>

            <div class="mt-6 border-t border-slate-200 pt-5">
                <h4 class="font-semibold text-rose-700">Restore from Backup</h4>
                <p class="mt-1 text-sm text-slate-600">Restoring permanently replaces the current application data and managed uploaded images. The backup must have been created by this system version. The database schema and login sessions are not changed.</p>
                <form method="POST" action="{{ route('system.restore') }}" enctype="multipart/form-data" class="mt-4 space-y-3" onsubmit="return confirm('This permanently replaces current system data and uploaded images. Continue only if you have selected the correct backup.');">
                    @csrf
                    <div>
                        <label for="system-backup-file" class="mb-1 block text-sm font-medium text-slate-700">Backup ZIP file (maximum 100 MB)</label>
                        <input id="system-backup-file" type="file" name="backup" accept=".zip,application/zip" required class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700">
                        @error('backup')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="restore-confirmation" class="mb-1 block text-sm font-medium text-slate-700">Type RESTORE to confirm data replacement</label>
                        <input id="restore-confirmation" type="text" name="confirmation" autocomplete="off" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @error('confirmation')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700">Restore Backup</button>
                </form>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h3 class="font-semibold text-slate-800">System Identity</h3>
            <p class="mt-1 text-sm text-slate-500">Update the names and icon shown across the system.</p>

            <form method="POST" action="{{ route('system.branding') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="barangay_name" class="mb-1 block text-sm font-medium text-slate-700">Barangay name</label>
                        <input id="barangay_name" name="barangay_name" type="text" value="{{ old('barangay_name', $systemBranding['barangay_name']) }}" maxlength="100" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label for="system_name" class="mb-1 block text-sm font-medium text-slate-700">System name</label>
                        <input id="system_name" name="system_name" type="text" value="{{ old('system_name', $systemBranding['system_name']) }}" maxlength="100" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-900 text-xl text-white">
                        @if ($systemBranding['icon_url'])
                            <img src="{{ $systemBranding['icon_url'] }}" alt="Current system icon" class="h-full w-full bg-white object-contain">
                        @else
                            <span>🏛️</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <label for="brand_icon" class="mb-1 block text-sm font-medium text-slate-700">System icon</label>
                        <input id="brand_icon" name="brand_icon" type="file" accept="image/*" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                        <p class="mt-1 text-xs text-slate-400">PNG, JPG, or other image; maximum 2 MB.</p>
                    </div>
                </div>

                @if ($systemBranding['icon_url'])
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remove_brand_icon" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Use the default icon
                    </label>
                @endif

                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Save identity</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
            <h3 class="font-semibold text-slate-800">Upload Background</h3>
            <form method="POST" action="{{ route('system.upload') }}" enctype="multipart/form-data" class="flex gap-3">
                @csrf
                <input type="file" name="backgroundFile" accept="image/*" required class="flex-1 text-sm">
                <button class="rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-medium">Upload</button>
            </form>
            <p class="text-xs text-slate-400">Max {{ \App\Http\Controllers\SystemController::UPLOAD_LIMIT }} images, 5MB each.</p>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-semibold text-slate-800">Login Background</h3>
                <form method="POST" action="{{ route('system.set-default') }}">
                    @csrf
                    <input type="hidden" name="target" value="login_background">
                    <input type="hidden" name="value" value="default">
                    <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium {{ $loginSetting === 'default' ? 'border-slate-800 bg-slate-800 text-white' : 'text-slate-700 hover:bg-slate-50' }}">
                        {{ $loginSetting === 'default' ? 'Using default image' : 'Use default image' }}
                    </button>
                </form>
            </div>
            <div class="grid sm:grid-cols-3 gap-4">
                @forelse ($images as $img)
                    <div class="border border-slate-200 rounded-lg overflow-hidden">
                        @if ($img->is_available)
                            <img src="{{ $img->url }}" alt="{{ $img->original_name }}" class="h-32 w-full object-cover">
                        @else
                            <div class="flex h-32 items-center justify-center bg-slate-100 px-4 text-center text-xs text-slate-500">Image file is missing. Upload it again to use it.</div>
                        @endif
                        <div class="p-2 space-y-1">
                            <p class="text-xs text-slate-500 truncate">{{ $img->original_name }}</p>
                            <div>
                                <form method="POST" action="{{ route('system.set-default') }}" class="flex-1">
                                    @csrf
                                    <input type="hidden" name="target" value="login_background">
                                    <input type="hidden" name="value" value="{{ $img->id }}">
                                    <button @disabled(!$img->is_available) class="w-full rounded border border-slate-300 py-1.5 text-xs font-medium disabled:cursor-not-allowed disabled:opacity-40 {{ $loginSetting == $img->id ? 'border-brand-300 bg-brand-50 text-brand-700' : '' }}">
                                        {{ $loginSetting == $img->id ? 'Using for login' : 'Use for login' }}
                                    </button>
                                </form>
                            </div>
                            <form method="POST" action="{{ route('system.destroy', $img->id) }}" onsubmit="return confirm('Delete this background?')">
                                @csrf @method('DELETE')
                                <button class="w-full text-xs text-rose-600 py-1">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 col-span-3">No backgrounds uploaded yet</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
