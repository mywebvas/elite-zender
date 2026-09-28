<x-layouts.bare :title="__('Unsubscribe')">
    <div class="w-full max-w-md">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xl dark:border-white/10 dark:bg-slate-900">
            <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-500/10">
                <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>

            @if ($alreadyUnsubscribed)
                <h1 class="text-xl font-semibold text-slate-900 dark:text-white">You're already unsubscribed</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ $contact->email }} will not receive further messages from this sender.
                </p>
            @else
                <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Unsubscribe from this list?</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    We'll stop sending messages to <span class="font-medium text-slate-700 dark:text-slate-200">{{ $contact->email }}</span> straight away.
                </p>

                <form method="POST" action="{{ $actionUrl }}" class="mt-6">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                        Confirm unsubscribe
                    </button>
                </form>

                <p class="mt-4 text-xs text-slate-400">Clicked by mistake? Simply close this page — nothing has changed yet.</p>
            @endif
        </div>
    </div>
</x-layouts.bare>
