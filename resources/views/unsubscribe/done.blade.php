<x-layouts.bare :title="__('Unsubscribed')">
    <div class="w-full max-w-md">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xl dark:border-white/10 dark:bg-slate-900">
            <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 dark:bg-emerald-500/10">
                <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-white">You've been unsubscribed</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                {{ $contact->email }} has been removed. You won't receive further messages from this sender.
            </p>
        </div>
    </div>
</x-layouts.bare>
