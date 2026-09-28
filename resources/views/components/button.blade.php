@props([
    'variant' => 'primary',
    'type'    => 'button',
    'size'    => 'md',
    'href'    => null,
    'loading' => false,
    'icon'    => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-semibold rounded-xl border transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-[#050508] disabled:opacity-50 disabled:pointer-events-none select-none active:scale-[0.97]';

    $sizes = [
        'sm' => 'px-3.5 py-2 text-xs min-h-[32px]',
        'md' => 'px-4   py-2.5 text-sm min-h-[40px]',
        'lg' => 'px-6   py-3   text-base min-h-[48px]',
        'xl' => 'px-8   py-4   text-base min-h-[56px]',
    ];

    $variants = [
        'primary'   => 'bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white border-transparent shadow-[0_1px_0_rgba(255,255,255,0.12)_inset,0_2px_8px_rgba(79,70,229,0.25)]',
        'gradient'  => 'text-white border-transparent btn-gradient',
        'secondary' => 'bg-white dark:bg-white/[0.06] hover:bg-slate-50 dark:hover:bg-white/[0.1] text-slate-700 dark:text-slate-200 border-slate-200 dark:border-white/10 shadow-xs',
        'ghost'     => 'bg-transparent hover:bg-slate-100 dark:hover:bg-white/[0.06] text-slate-600 dark:text-slate-300 border-transparent',
        'danger'    => 'bg-rose-600 hover:bg-rose-500 active:bg-rose-700 text-white border-transparent shadow-[0_1px_0_rgba(255,255,255,0.12)_inset]',
        'success'   => 'bg-emerald-600 hover:bg-emerald-500 text-white border-transparent shadow-xs',
    ];

    $classes = implode(' ', [$base, $sizes[$size] ?? $sizes['md'], $variants[$variant] ?? $variants['primary']]);
    $tag     = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    @if(!$href) type="{{ $type }}" @endif
    {{ $attributes->merge(['class' => $classes]) }}
    @if($loading) aria-busy="true" disabled @endif
>
    @if($loading)
        <svg class="w-4 h-4 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
    @elseif($icon)
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
        </svg>
    @endif
    {{ $slot }}
</{{ $tag }}>
