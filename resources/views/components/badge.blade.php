@props([
    'color' => 'slate', // slate | indigo | emerald | rose | amber | sky | purple
    'size'  => 'sm',    // sm | md
])

@php
    $colors = [
        'slate'   => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        'indigo'  => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300',
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300',
        'rose'    => 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300',
        'amber'   => 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300',
        'sky'     => 'bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-300',
        'purple'  => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
    ];

    $sizes = [
        'sm' => 'px-2 py-0.5 text-[11px]',
        'md' => 'px-3 py-1   text-[12px]',
    ];

    $base = 'inline-flex items-center font-medium rounded-full ' . ($colors[$color] ?? $colors['slate']) . ' ' . ($sizes[$size] ?? $sizes['sm']);
@endphp

<span {{ $attributes->merge(['class' => $base]) }}>
    {{ $slot }}
</span>
