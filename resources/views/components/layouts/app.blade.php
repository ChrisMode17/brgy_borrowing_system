<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · {{ $systemBranding['barangay_name'] }} {{ $systemBranding['system_name'] }}</title>
    <link rel="icon" href="{{ $systemBranding['icon_url'] ?? asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: { brand: { 50:'#eff6ff',100:'#dbeafe',500:'#2563eb',600:'#1d4ed8',700:'#1e3a8a',900:'#0f172a' } }
                }
            }
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-screen overflow-hidden font-sans antialiased" x-data="{ sidebarOpen: false, helpOpen: false, sidebarCollapsed: false }">
    <div class="flex h-full min-h-0 bg-slate-100">
        @include('components.sidebar')

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            @include('components.header')

            <main data-app-main class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6 lg:px-8">
                <div class="mx-auto min-h-full w-full max-w-[1600px]">
                    <x-flash-messages />
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    <div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak class="fixed inset-0 z-30 bg-slate-950/45 lg:hidden"></div>
</body>
</html>
