<x-layouts.guest :title="'Login'">
    <div class="grid min-h-screen bg-white lg:grid-cols-2">
        <main class="flex min-h-screen items-center justify-center px-6 py-12 sm:px-12 lg:px-16">
            <div class="w-full max-w-md">
                <div class="mb-8">
                    <div class="mb-7 flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-900 text-lg text-white">
                            @if ($systemBranding['icon_url'])
                                <img src="{{ $systemBranding['icon_url'] }}" alt="{{ $systemBranding['barangay_name'] }} icon" class="h-full w-full bg-white object-contain">
                            @else
                                <span>🏛️</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $systemBranding['barangay_name'] }}</p>
                            <p class="truncate text-lg font-bold text-slate-900">{{ $systemBranding['system_name'] }}</p>
                        </div>
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight text-slate-900">Welcome back!</h1>
                    <p class="mt-2 text-sm text-slate-600">Log in to your secure dashboard</p>
                </div>

                <x-flash-messages />

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label for="username" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-800">Email or username</label>
                        <input id="username" type="text" name="username" value="{{ old('username') }}" placeholder="Enter your email or username" autocomplete="username" required autofocus
                               class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-900 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200">
                    </div>
                    <div>
                        <label for="password" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-800">Password</label>
                        <input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required
                               class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-900 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200">
                    </div>

                    <button type="submit" class="flex h-12 w-full items-center justify-center gap-2 rounded-full bg-slate-900 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                        <span aria-hidden="true">🔒</span>
                        <span>Login</span>
                    </button>
                </form>

                <p class="mt-7 text-center text-sm">
                    <a href="{{ route('password.request') }}" class="font-medium text-blue-700 hover:text-blue-800 hover:underline">Forgot password?</a>
                </p>
            </div>
        </main>

        <aside class="relative hidden min-h-screen overflow-hidden bg-slate-200 lg:block" aria-hidden="true">
            <img src="{{ $loginBackgroundUrl ?? 'https://images.unsplash.com/photo-1470770841072-f978cf4d019e?auto=format&fit=crop&w=1800&q=85' }}" alt="" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-slate-900/10"></div>
        </aside>
    </div>
</x-layouts.guest>
