@props([
    'variant' => 'slate',
    'color'   => null,
    'size'    => 'sm',
])
@php
    $c = $color ?? $variant;
    $palette = [
        'slate'   => 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400',
        'indigo'  => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-400',
        'primary' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-400',
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
        'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
        'rose'    => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
        'danger'  => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
        'amber'   => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
        'sky'     => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-400',
        'violet'  => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-400',
        'purple'  => 'bg-purple-100 text-purple-700 dark:bg-purple-500/15 dark:text-purple-400',
    ];
    $cls = $palette[$c] ?? $palette['slate'];
@endphp
<span {{ $attributes->merge(['class' => 'badge-base ' . $cls]) }}>
    {{ $slot }}
</span>
