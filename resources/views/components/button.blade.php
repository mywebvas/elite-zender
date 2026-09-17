@props([
    'variant' => 'primary',  // primary | secondary | ghost | danger
    'type'    => 'button',
    'size'    => 'md',       // sm | md | lg
    'href'    => null,
    'loading' => false,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-medium rounded-lg border transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-slate-900 disabled:opacity-50 disabled:cursor-not-allowed select-none';

    $sizes = [
        'sm' => 'px-3 py-1.5 text-[12px] min-h-[32px]',
        'md' => 'px-4 py-2   text-[13px] min-h-[40px]',
        'lg' => 'px-6 py-3   text-[14px] min-h-[48px]',
    ];

    $variants = [
        'primary'   => 'bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white border-transparent shadow-sm',
        'secondary' => 'bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-600 shadow-sm',
        'ghost'     => 'bg-transparent hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 border-transparent',
        'danger'    => 'bg-rose-600 hover:bg-rose-500 active:bg-rose-700 text-white border-transparent shadow-sm',
    ];

    $classes = implode(' ', [$base, $sizes[$size] ?? $sizes['md'], $variants[$variant] ?? $variants['primary']]);
    $tag     = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    @if(!$href) type="{{ $type }}" @endif
    {{ $attributes->merge(['class' => $classes]) }}
    @if($loading) aria-busy="true" @endif
>
    @if($loading)
        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
    @endif
    {{ $slot }}
</{{ $tag }}>
