@props([
    'label',
    'value',
    'hint' => null,
    'trend' => null,      // signed percentage, e.g. 12.4 or -3.1
    'tone' => 'brand',
    'icon' => null,
])

{{--
    A single KPI. Figures use tabular numerals so a row of stats does not
    shuffle sideways as the digits change.
--}}
@php
    $tones = [
        'brand' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
        'success' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
        'warning' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
        'danger' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
        'accent' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'card flex flex-col gap-3 p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="stat-label">{{ $label }}</p>
        @if($icon)
            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['brand'] }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
                </svg>
            </div>
        @endif
    </div>

    <div>
        <p class="stat-value">{{ $value }}</p>

        <div class="mt-1 flex items-center gap-2">
            @if($trend !== null)
                <span @class([
                    'inline-flex items-center gap-0.5 text-xs font-semibold tabular-nums',
                    'text-emerald-600 dark:text-emerald-400' => $trend > 0,
                    'text-rose-600 dark:text-rose-400' => $trend < 0,
                    'text-slate-400' => $trend == 0,
                ])>
                    @if($trend != 0)
                        <svg class="h-3 w-3 {{ $trend < 0 ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                        </svg>
                    @endif
                    {{ abs($trend) }}%
                </span>
            @endif

            @if($hint)
                <p class="truncate text-xs text-slate-400 dark:text-slate-500">{{ $hint }}</p>
            @endif
        </div>
    </div>
</div>
