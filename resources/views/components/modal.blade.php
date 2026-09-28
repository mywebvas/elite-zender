@props(['title' => '', 'size' => 'md'])
@php
$maxw = ['sm'=>'sm:max-w-sm','md'=>'sm:max-w-lg','lg'=>'sm:max-w-2xl','xl'=>'sm:max-w-4xl'][$size] ?? 'sm:max-w-lg';
@endphp
<div x-cloak {{ $attributes }} class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    {{-- Backdrop --}}
    <div x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-4"
                 @click.away="$el.closest('[role=dialog]').dispatchEvent(new CustomEvent('close-modal',{bubbles:true}))"
                 @keydown.escape.window="$el.closest('[role=dialog]').dispatchEvent(new CustomEvent('close-modal',{bubbles:true}))"
                 class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-[#0f0f14] text-left shadow-xl ring-1 ring-black/[0.08] dark:ring-white/[0.08] transition-all sm:my-8 sm:w-full {{ $maxw }}">

                {{-- Header --}}
                @if($title)
                <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200 dark:border-white/[0.06]">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white" id="modal-title">{{ $title }}</h3>
                    <button type="button"
                            @click="$el.closest('[role=dialog]').dispatchEvent(new CustomEvent('close-modal',{bubbles:true}))"
                            class="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                @endif

                <div class="px-6 py-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</div>
