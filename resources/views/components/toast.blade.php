<div
    x-data="{
        toasts: [],
        add(msg, type='success', duration=4500) {
            const id = Date.now();
            this.toasts.push({ id, msg, type, progress: 100 });
            const interval = setInterval(() => {
                const t = this.toasts.find(t => t.id === id);
                if (t) { t.progress -= (100 / (duration / 100)); if (t.progress <= 0) { this.remove(id); clearInterval(interval); } }
                else clearInterval(interval);
            }, 100);
            setTimeout(() => this.remove(id), duration);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
        init() {
            window.$toast = (msg, type='success') => this.add(msg, type);
            @if(session('success')) this.add(@json(session('success')), 'success'); @endif
            @if(session('error'))   this.add(@json(session('error')),   'error');   @endif
            @if(session('warning')) this.add(@json(session('warning')), 'warning'); @endif
            @if(session('info'))    this.add(@json(session('info')),    'info');    @endif
        }
    }"
    class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 pointer-events-none max-w-sm w-full"
    role="region"
    aria-label="Notifications"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            class="pointer-events-auto relative overflow-hidden rounded-2xl shadow-xl ring-1 bg-white dark:bg-[#111118]"
            :class="{
                'ring-emerald-200 dark:ring-emerald-500/20': toast.type === 'success',
                'ring-rose-200 dark:ring-rose-500/20':     toast.type === 'error',
                'ring-amber-200 dark:ring-amber-500/20':   toast.type === 'warning',
                'ring-indigo-200 dark:ring-indigo-500/20': toast.type === 'info',
            }"
        >
            <div class="flex items-start gap-3 px-4 py-3.5">
                {{-- Icon --}}
                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                     :class="{
                         'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400': toast.type === 'success',
                         'bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400':           toast.type === 'error',
                         'bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400':       toast.type === 'warning',
                         'bg-indigo-100 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400':   toast.type === 'info',
                     }">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path x-show="toast.type==='success'" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        <path x-show="toast.type==='error'"   stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        <path x-show="toast.type==='warning'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        <path x-show="toast.type==='info'"    stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>

                {{-- Message --}}
                <p class="flex-1 text-sm font-medium text-slate-800 dark:text-slate-100 pt-1" x-text="toast.msg"></p>

                {{-- Close --}}
                <button @click="remove(toast.id)"
                        class="flex-shrink-0 mt-0.5 rounded-lg p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Progress bar --}}
            <div class="absolute bottom-0 left-0 h-0.5 rounded-full transition-all duration-100"
                 :style="'width:' + toast.progress + '%'"
                 :class="{
                     'bg-emerald-400 dark:bg-emerald-500': toast.type === 'success',
                     'bg-rose-400 dark:bg-rose-500':      toast.type === 'error',
                     'bg-amber-400 dark:bg-amber-500':    toast.type === 'warning',
                     'bg-indigo-400 dark:bg-indigo-500':  toast.type === 'info',
                 }"></div>
        </div>
    </template>
</div>
