<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $systemBranding['system_name'] }} · {{ $systemBranding['barangay_name'] }} {{ $systemBranding['system_name'] }}</title>
    <link rel="icon" href="{{ $systemBranding['icon_url'] ?? asset('favicon.ico') }}">
    @vite(['resources/css/app.css'])
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased bg-slate-100 min-h-screen">
    {{ $slot }}
</body>
</html>
