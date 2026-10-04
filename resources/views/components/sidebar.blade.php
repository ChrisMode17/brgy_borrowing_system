@php
    $user = auth()->user();
    $routeName = request()->route()?->getName() ?? 'dashboard';
    $nav = [
        ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => '🏠'],
        ['route' => 'inventory.index', 'label' => 'Inventory', 'icon' => '📦'],
        ['route' => 'borrow.create', 'label' => 'Borrow / Reserve', 'icon' => '📝'],
        ['route' => 'reservation.index', 'label' => 'Reservations', 'icon' => '📅'],
        ['route' => 'return.index', 'label' => 'Returns', 'icon' => '↩️'],
    ];
    $adminNav = [
        ['route' => 'equipment.index', 'label' => 'Manage Equipment', 'icon' => '🛠️'],
        ['route' => 'users.index', 'label' => 'Manage Users', 'icon' => '👥'],
        ['route' => 'audit.index', 'label' => 'Audit Trail', 'icon' => '📜'],
        ['route' => 'system.index', 'label' => 'System Settings', 'icon' => '⚙️'],
    ];
    $helpContent = [
        'dashboard' => [
            'title' => 'Dashboard Overview',
            'intro' => 'Overview of equipment status and recent activity for the day.',
            'items' => [
                'Track equipment counts, active borrows, and pending reservations at a glance.',
                'Use the dashboard to spot shortages, delays, or recent movement quickly.',
                'Open the inventory or reservation pages for more detailed actions.'
            ]
        ],
        'inventory.index' => [
            'title' => 'Inventory Guide',
            'intro' => 'View and manage your equipment stock and categories.',
            'items' => [
                'Check item availability before creating a borrow or reservation.',
                'Review the quantity, category, and equipment details for each item.',
                'Use search and filters to find the right equipment faster.'
            ]
        ],
        'borrow.create' => [
            'title' => 'Borrow / Reserve',
            'intro' => 'Create a borrow request or reservation for equipment.',
            'items' => [
                'Select the equipment and quantity needed for the borrower.',
                'Add borrower details and the scheduled borrow and return dates.',
                'Submit the request after making sure the dates and items are correct.'
            ]
        ],
        'reservation.index' => [
            'title' => 'Reservations',
            'intro' => 'Pending reservations are confirmed once the scheduled time arrives.',
            'items' => [
                'Review all pending reservations before the scheduled borrow date.',
                'Confirm the borrow only after the scheduled borrow time is reached.',
                'Cancel a reservation if the request is no longer needed.'
            ]
        ],
        'return.index' => [
            'title' => 'Returns',
            'intro' => 'Record and monitor equipment that has been returned.',
            'items' => [
                'Match the returned items to the borrower and reference number.',
                'Check for late or incomplete equipment returns.',
                'Use this page to update the system after physical return verification.'
            ]
        ],
        'equipment.index' => [
            'title' => 'Manage Equipment',
            'intro' => 'Add, edit, and maintain equipment records and stock levels.',
            'items' => [
                'Add new equipment or update existing item information.',
                'Adjust stock counts and categories to keep records accurate.',
                'Use this page to prepare equipment for borrowing and reservations.'
            ]
        ],
        'users.index' => [
            'title' => 'Manage Users Guide',
            'intro' => 'Admin-only page for managing system accounts and access levels.',
            'items' => [
                'Add, edit, or remove user accounts.',
                'Assign roles and access levels to control what each user can do.',
                'Deactivate an account to revoke access without deleting its history.'
            ]
        ],
        'audit.index' => [
            'title' => 'Audit Trail',
            'intro' => 'Review important system actions and record history.',
            'items' => [
                'Track reservations, borrow confirmations, and equipment updates.',
                'Check who performed an action and when it happened.',
                'Use the logs to investigate issues or review past activity.'
            ]
        ],
        'system.index' => [
            'title' => 'System Settings',
            'intro' => 'Adjust the operation and configuration of the system.',
            'items' => [
                'Update core system settings to fit your barangay workflow.',
                'Review settings that affect equipment and user operations.',
                'Keep configuration consistent to avoid operational confusion.'
            ]
        ],
        'profile.edit' => [
            'title' => 'Profile Settings',
            'intro' => 'Update your user account and personal information.',
            'items' => [
                'Edit your information and contact details.',
                'Review your account details for accuracy and completeness.',
                'Keep your profile current to improve account management.'
            ]
        ],
    ];
    $currentHelp = $helpContent[$routeName] ?? [
        'title' => 'Need help?',
        'intro' => 'This section provides quick guidance for the current page.',
        'items' => [
            'Use the menu to navigate to the correct feature page.',
            'Review each section carefully before making changes.',
            'Contact an administrator if you need additional assistance.'
        ]
    ];
@endphp
<aside
    data-app-sidebar
    class="fixed inset-y-0 left-0 z-40 border-r border-slate-200 bg-slate-900 text-slate-100 shadow-lg transition-all duration-200 lg:static lg:z-auto lg:h-full lg:shrink-0 lg:translate-x-0"
    :class="[ sidebarOpen ? 'translate-x-0' : '-translate-x-full', sidebarCollapsed ? 'w-20' : 'w-72' ]"
>
    <div class="flex h-16 items-center border-b border-white/10 transition-all duration-200" :class="sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-5'">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white/10 text-lg">
            @if ($systemBranding['icon_url'])
                <img src="{{ $systemBranding['icon_url'] }}" alt="{{ $systemBranding['barangay_name'] }} icon" class="h-full w-full object-contain">
            @else
                <span>🏛️</span>
            @endif
        </div>
        <div x-show="!sidebarCollapsed" x-cloak>
            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-300">{{ $systemBranding['barangay_name'] }}</div>
            <div class="max-w-48 truncate text-base font-bold tracking-tight text-white">{{ $systemBranding['system_name'] }}</div>
        </div>
    </div>

    <nav class="space-y-1 py-4" :class="sidebarCollapsed ? 'px-2' : 'px-3'">
        @foreach ($nav as $item)
            <a href="{{ route($item['route']) }}"
               class="flex items-center rounded-lg py-2.5 text-sm font-medium transition" 
               :class="sidebarCollapsed ? 'justify-center px-2 {{ request()->routeIs($item['route']) ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}' : 'gap-3 px-3 {{ request()->routeIs($item['route']) ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}'"
               >
                <span class="text-base">{{ $item['icon'] }}</span>
                <span x-show="!sidebarCollapsed" x-cloak>{{ $item['label'] }}</span>
            </a>
        @endforeach

        @if ($user && $user->isAdmin())
            <div class="pt-4">
                <p x-show="!sidebarCollapsed" x-cloak class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">Administration</p>
                @foreach ($adminNav as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center rounded-lg py-2.5 text-sm font-medium transition" 
                       :class="sidebarCollapsed ? 'justify-center px-2 {{ request()->routeIs($item['route']) ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}' : 'gap-3 px-3 {{ request()->routeIs($item['route']) ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}'"
                       >
                        <span class="text-base">{{ $item['icon'] }}</span>
                        <span x-show="!sidebarCollapsed" x-cloak>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </nav>

    <div class="absolute bottom-4 flex" :class="sidebarCollapsed ? 'inset-x-0 justify-center' : 'left-4'">
        <div class="relative">
            <button
                type="button"
                @click="helpOpen = !helpOpen"
                @click.outside="helpOpen = false"
                class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-500/60 bg-slate-800 text-sm font-bold text-slate-100 shadow-md transition hover:bg-slate-700 focus:outline-none"
                aria-label="Open help guide"
            >
                ?
            </button>
        </div>
    </div>
</aside>

<div
    data-app-help
    x-show="helpOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 p-4"
    @click.self="helpOpen = false"
>
    <div class="w-full max-w-160 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between bg-slate-900 px-5 py-4 text-white">
            <div class="flex items-center gap-3">
                <span class="text-xl">👥</span>
                <h3 class="text-2xl font-bold">{{ $currentHelp['title'] }}</h3>
            </div>
            <button type="button" @click="helpOpen = false" class="text-2xl font-light text-slate-300 hover:text-white">×</button>
        </div>

        <div class="bg-white p-6 text-slate-700">
            <p class="mb-5 text-base leading-7 text-slate-600">
                {{ $currentHelp['intro'] }}
            </p>

            <ul class="space-y-4">
                @foreach ($currentHelp['items'] as $item)
                    <li class="flex items-start gap-3 text-base leading-7 text-slate-700">
                        <span class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-100 text-[11px] font-bold text-blue-600">✓</span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
