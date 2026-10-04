@php $user = auth()->user(); @endphp
<header data-app-header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur-sm">
    <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
            <button class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-slate-600 transition hover:bg-slate-100 lg:hidden" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle navigation">
                <span class="text-xl leading-none">☰</span>
            </button>

            <button class="hidden rounded-lg border border-slate-200 bg-slate-50 p-2 text-slate-600 transition hover:bg-slate-100 lg:inline-flex" @click="sidebarCollapsed = !sidebarCollapsed" aria-label="Toggle sidebar collapse">
                <span class="text-xl leading-none" x-text="sidebarCollapsed ? '→' : '←'">←</span>
            </button>

            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $systemBranding['barangay_name'] }}</p>
                <h1 class="text-lg font-bold tracking-tight text-slate-800">{{ $title ?? 'Dashboard' }}</h1>
            </div>
        </div>

        <div class="flex items-center gap-4" x-data="{ open: false }">
            <div class="hidden text-right sm:block">
                <p class="text-sm font-semibold text-slate-800">{{ $user?->first_name }} {{ $user?->last_name }}</p>
                <p class="text-xs text-slate-500">{{ $user?->roleName() }}</p>
            </div>

            <div class="relative">
                <button @click="open = !open" class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-slate-800 text-sm font-bold text-white ring-2 ring-white">
                    @if ($user?->profilePhotoUrl())
                        <img src="{{ $user->profilePhotoUrl() }}" alt="" class="h-full w-full object-cover">
                    @else
                        {{ strtoupper(substr($user?->first_name ?? 'U', 0, 1)) }}
                    @endif
                </button>

                <div x-show="open" @click.outside="open = false" x-cloak
                     class="absolute right-0 mt-2 w-48 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">Profile Settings</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2.5 text-left text-sm text-rose-600 transition hover:bg-rose-50">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
