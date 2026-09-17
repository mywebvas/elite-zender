{{--
    x-toast — Global toast stack
    Position: top-center mobile, bottom-right desktop (docs/07-PWA-SPEC.md §4.1)
    Max 3 visible; success auto-dismisses after 3s; error persists until dismissed.

    Usage:
        window.$toast('Message here')                    // success (auto-dismiss)
        window.$toast('Error!', 'error')                 // error (persists)
        window.$toast('Heads up', 'info', 0)             // info (persists)
        window.$toast('Update available', 'warning', 0) // warning (persists)

    Also renders session flash messages automatically.
--}}

<div
    x-data="{
        MAX: 3,
        get visible() {
            return $store.toastQueue.items.slice(0, this.MAX);
        },
        dismiss(id) {
            $store.toastQueue.items = $store.toastQueue.items.filter(t => t.id !== id);
        }
    }"
    class="fixed z-[9999] flex flex-col gap-2 pointer-events-none
           top-4 inset-x-4 sm:inset-x-auto sm:right-4 sm:w-80
           sm:top-auto sm:bottom-4"
    aria-live="polite"
    aria-atomic="false"
    role="status"
>
    <template x-for="toast in visible" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            :class="{
                'bg-emerald-600 text-white': toast.type === 'success',
                'bg-rose-600 text-white':    toast.type === 'error',
                'bg-indigo-600 text-white':  toast.type === 'info',
                'bg-amber-500 text-white':   toast.type === 'warning',
            }"
            class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl shadow-md max-w-full"
        >
            {{-- Icon --}}
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path x-show="toast.type === 'success'" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                <path x-show="toast.type === 'error'"   stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                <path x-show="toast.type === 'info' || toast.type === 'warning'" stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>

            {{-- Message --}}
            <p class="flex-1 text-[13px] font-medium leading-snug" x-text="toast.message"></p>

            {{-- Dismiss button --}}
            <button
                @click="dismiss(toast.id)"
                class="flex-shrink-0 opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-1 focus:ring-offset-current rounded"
                aria-label="Dismiss notification"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>

    {{-- Session flash messages (success, error) --}}
    @if(session('success'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3000)"
            x-show="show"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl shadow-md bg-emerald-600 text-white"
        >
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="flex-1 text-[13px] font-medium">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl shadow-md bg-rose-600 text-white"
        >
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <p class="flex-1 text-[13px] font-medium">{{ session('error') }}</p>
            <button @click="show = false" class="flex-shrink-0 opacity-70 hover:opacity-100" aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif
</div>
