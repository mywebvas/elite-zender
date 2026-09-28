@props([
    'variant' => 'primary',
    'type'    => 'button',
    'size'    => 'md',
    'href'    => null,
    'loading' => false,
    'icon'    => null,
])

@php
    // focus-visible, not focus: a mouse user clicking a button should not be
    // left with a ring they did not ask for, while a keyboard user must always
    // see exactly where they are.
    $base = 'inline-flex items-center justify-center gap-2 font-semibold tracking-[-0.006em] rounded-xl border '
        .'transition-[box-shadow,transform,background-color,filter] duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] '
        .'focus:outline-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 '
        .'focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#050508] '
        .'disabled:opacity-55 disabled:pointer-events-none select-none active:scale-[0.985]';

    // 40px minimum on the default size; 44px on touch targets is the WCAG
    // guidance and the reason `lg` exists.
    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs min-h-[32px]',
        'md' => 'px-4 py-2.5 text-sm min-h-[40px]',
        'lg' => 'px-5 py-3 text-sm min-h-[44px]',
        'xl' => 'px-7 py-4 text-base min-h-[52px]',
    ];

    $variants = [
        'primary'   => 'bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white border-transparent shadow-[0_1px_0_rgba(255,255,255,0.14)_inset,0_4px_14px_-4px_rgba(79,70,229,0.5)] hover:shadow-[0_1px_0_rgba(255,255,255,0.14)_inset,0_8px_22px_-6px_rgba(79,70,229,0.55)] hover:-translate-y-px',
        'gradient'  => 'text-white border-transparent btn-gradient',
        'secondary' => 'bg-white dark:bg-white/[0.06] hover:bg-slate-50 dark:hover:bg-white/[0.1] text-slate-700 dark:text-slate-200 border-slate-200 dark:border-white/10 shadow-[0_1px_2px_rgba(15,23,42,0.04)] hover:border-slate-300 dark:hover:border-white/20',
        'ghost'     => 'bg-transparent hover:bg-slate-100 dark:hover:bg-white/[0.06] text-slate-600 dark:text-slate-300 border-transparent',
        'danger'    => 'bg-rose-600 hover:bg-rose-500 active:bg-rose-700 text-white border-transparent shadow-[0_1px_0_rgba(255,255,255,0.14)_inset,0_4px_14px_-4px_rgba(225,29,72,0.45)]',
        'danger-ghost' => 'bg-transparent hover:bg-rose-50 dark:hover:bg-rose-500/10 text-rose-600 dark:text-rose-400 border-transparent',
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
