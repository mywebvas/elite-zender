<x-layouts.guest header="That link is no longer valid"
                 subheader="Invitations expire, and each one can only be used once">

    <div class="space-y-5">
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            This usually means one of three things: the invitation has already been
            accepted, it has expired, or somebody revoked it. None of them are
            anything to worry about.
        </p>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Ask whoever invited you to send a fresh one — it takes them a few
                seconds from their team page.
            </p>
        </div>

        <a href="{{ route('login') }}"
           class="inline-flex w-full items-center justify-center rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            Sign in instead
        </a>
    </div>

</x-layouts.guest>
