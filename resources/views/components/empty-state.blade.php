@props([
    'heading' => 'Nothing here yet',
    'body'    => '',
])

{{--
    x-empty-state — "An invitation, never a dead end" (docs/07-PWA-SPEC.md §3)
    Slots: $slot (CTA area), $illustration (optional custom SVG override)
--}}

<div class="flex flex-col items-center justify-center py-16 px-6 text-center">

    {{-- Illustration --}}
    @isset($illustration)
        {{ $illustration }}
    @else
        <div class="w-20 h-20 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
            </svg>
        </div>
    @endisset

    {{-- Heading --}}
    <h3 class="text-[15px] font-semibold text-slate-900 dark:text-slate-100 mb-2">
        {{ $heading }}
    </h3>

    {{-- Body --}}
    @if($body)
        <p class="text-[13px] text-slate-500 dark:text-slate-400 max-w-sm leading-relaxed mb-6">
            {{ $body }}
        </p>
    @endif

    {{-- CTA slot --}}
    @if($slot->isNotEmpty())
        <div class="{{ $body ? 'mt-0' : 'mt-6' }}">
            {{ $slot }}
        </div>
    @endif
</div>
