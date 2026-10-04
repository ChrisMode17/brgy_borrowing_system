@php
    $map = [
        'available' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'borrowed' => 'bg-amber-50 text-amber-700 border-amber-200',
        'pending' => 'bg-purple-50 text-purple-700 border-purple-200',
        'returned' => 'bg-slate-100 text-slate-600 border-slate-200',
        'late' => 'bg-rose-50 text-rose-700 border-rose-200',
        'under_maintenance' => 'bg-orange-50 text-orange-700 border-orange-200',
        'broken' => 'bg-rose-50 text-rose-700 border-rose-200',
        'lost' => 'bg-slate-100 text-slate-500 border-slate-200',
    ];
    $key = strtolower(str_replace(' ', '_', $status ?? ''));
    $classes = $map[$key] ?? 'bg-slate-100 text-slate-600 border-slate-200';
    $label = ucfirst(str_replace('_', ' ', $status ?? ''));
@endphp
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $classes }}">{{ $label }}</span>
