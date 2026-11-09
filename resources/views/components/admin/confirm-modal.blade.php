<div x-data="{
        open: false,
        title: '',
        message: '',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        tone: 'danger',
        targetFormId: null,
        actionUrl: null,
        method: 'POST',
        init() {
            window.addEventListener('open-confirm-modal', (e) => {
                this.title = e.detail.title || 'Are you sure?';
                this.message = e.detail.message || 'This action cannot be undone.';
                this.confirmText = e.detail.confirmText || 'Confirm';
                this.cancelText = e.detail.cancelText || 'Cancel';
                this.tone = e.detail.tone || 'danger';
                this.targetFormId = e.detail.formId || null;
                this.actionUrl = e.detail.action || null;
                this.method = e.detail.method || 'POST';
                this.open = true;
            });
        },
        execute() {
            if (this.targetFormId) {
                const form = document.getElementById(this.targetFormId);
                if (form) {
                    this.open = false;
                    form.submit();
                    return;
                }
            }
            if (this.actionUrl) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = this.actionUrl;
                const csrf = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content');
                if (csrf) {
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = csrf;
                    form.appendChild(csrfInput);
                }
                if (this.method && this.method.toUpperCase() !== 'POST') {
                    const methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = this.method.toUpperCase();
                    form.appendChild(methodInput);
                }
                document.body.appendChild(form);
                this.open = false;
                form.submit();
            }
        }
     }"
     x-show="open"
     x-cloak
     @keydown.escape.window="open = false"
     class="relative z-50"
     aria-labelledby="modal-title" role="dialog" aria-modal="true">

    {{-- Backdrop --}}
    <div x-show="open"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/75 backdrop-blur-sm transition-opacity"
         @click="open = false"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-20 flex items-center justify-center">
        <div x-show="open"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.outside="open = false"
             class="relative w-full max-w-md transform overflow-hidden rounded-2xl border border-white/[0.08] bg-[#0d0d16] p-6 text-left shadow-2xl transition-all">

            <div class="flex items-start gap-4">
                {{-- Tone Icon --}}
                <div :class="{
                        'bg-rose-500/15 text-rose-400 border-rose-500/20': tone === 'danger',
                        'bg-amber-500/15 text-amber-400 border-amber-500/20': tone === 'warning',
                        'bg-indigo-500/15 text-indigo-400 border-indigo-500/20': tone === 'primary'
                     }"
                     class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl border">
                    <template x-if="tone === 'danger'">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </template>
                    <template x-if="tone === 'warning'">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </template>
                    <template x-if="tone === 'primary'">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </template>
                </div>

                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-bold text-white tracking-tight" id="modal-title" x-text="title"></h3>
                    <p class="mt-1.5 text-xs leading-relaxed text-slate-400" x-text="message"></p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-end gap-2.5">
                <button type="button"
                        @click="open = false"
                        class="rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2 text-xs font-semibold text-slate-300 transition hover:bg-white/[0.08] hover:text-white">
                    <span x-text="cancelText"></span>
                </button>
                <button type="button"
                        @click="execute()"
                        :class="{
                            'bg-rose-600 hover:bg-rose-500 text-white shadow-rose-950/50': tone === 'danger',
                            'bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-amber-950/50': tone === 'warning',
                            'bg-indigo-600 hover:bg-indigo-500 text-white shadow-indigo-950/50': tone === 'primary'
                        }"
                        class="rounded-xl px-4 py-2 text-xs font-bold shadow-sm transition">
                    <span x-text="confirmText"></span>
                </button>
            </div>
        </div>
    </div>
</div>
