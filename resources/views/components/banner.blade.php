@props(['tone' => 'info', 'title' => null, 'dismissible' => false])

{{--
    A single banner component for every "you should know this" message.

    Tone maps to meaning, not to decoration: danger means something is broken
    right now, warning means it will break soon, success confirms an action,
    info is context. Using them interchangeably is how people learn to ignore
    banners entirely.
--}}
@php
    $tones = [
        'info' => [
            'shell' => 'border-sky-200 bg-sky-50 dark:border-sky-500/20 dark:bg-sky-500/10',
            'icon' => 'text-sky-600 dark:text-sky-400',
            'text' => 'text-sky-900 dark:text-sky-200',
            'path' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'success' => [
            'shell' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/20 dark:bg-emerald-500/10',
            'icon' => 'text-emerald-600 dark:text-emerald-400',
            'text' => 'text-emerald-900 dark:text-emerald-200',
            'path' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'warning' => [
            'shell' => 'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/10',
            'icon' => 'text-amber-600 dark:text-amber-400',
            'text' => 'text-amber-900 dark:text-amber-200',
            'path' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        ],
        'danger' => [
            'shell' => 'border-rose-200 bg-rose-50 dark:border-rose-500/20 dark:bg-rose-500/10',
            'icon' => 'text-rose-600 dark:text-rose-400',
            'text' => 'text-rose-900 dark:text-rose-200',
            'path' => 'M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
    ];

    $t = $tones[$tone] ?? $tones['info'];
@endphp

<div x-data="{ shown: true }" x-show="shown" x-cloak
     {{ $attributes->merge(['class' => 'rounded-2xl border p-4 '.$t['shell']]) }}
     role="{{ in_array($tone, ['danger', 'warning'], true) ? 'alert' : 'status' }}">
    <div class="flex gap-3">
        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 {{ $t['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $t['path'] }}"/>
        </svg>

        <div class="min-w-0 flex-1 {{ $t['text'] }}">
            @if($title)
                <p class="text-sm font-semibold">{{ $title }}</p>
                <div class="mt-0.5 text-sm opacity-90">{{ $slot }}</div>
            @else
                <div class="text-sm font-medium">{{ $slot }}</div>
            @endif

            @isset($actions)
                <div class="mt-3 flex flex-wrap gap-2">{{ $actions }}</div>
            @endisset
        </div>

        @if($dismissible)
            <button type="button" @click="shown = false"
                    class="-m-1 h-7 w-7 flex-shrink-0 rounded-lg p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100 dark:hover:bg-white/10 {{ $t['icon'] }}"
                    aria-label="Dismiss">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        @endif
    </div>
</div>
