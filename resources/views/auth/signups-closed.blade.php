<x-layouts.guest header="Signups are paused"
                 subheader="We're not opening new workspaces right now">

    <div class="space-y-5">
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            This is temporary and nothing to do with you. Existing workspaces are
            unaffected — if you already have an account you can sign in as normal.
        </p>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Want to be told the moment we reopen? Email
                <a href="mailto:{{ $supportEmail }}"
                   class="font-semibold text-brand-600 underline decoration-brand-300 underline-offset-2 hover:text-brand-700 dark:text-brand-400">{{ $supportEmail }}</a>
                and we'll put you at the front of the queue.
            </p>
        </div>

        <a href="{{ route('login') }}"
           class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
            Sign in to an existing workspace
        </a>
    </div>

</x-layouts.guest>
