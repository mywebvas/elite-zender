<x-layouts.guest header="You are already signed in"
                 subheader="An account belongs to one workspace">

    <div class="space-y-5">
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            You are signed in as
            <span class="font-semibold text-slate-900 dark:text-white">{{ $current->email }}</span>,
            and this invitation is for
            <span class="font-semibold text-slate-900 dark:text-white">{{ $invitation->email }}</span>.
        </p>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
            <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                Each account belongs to exactly one workspace. To accept this
                invitation, sign out first and then open the link again — it stays
                valid until {{ $invitation->expires_at->toFormattedDayDateString() }}.
            </p>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                Sign out and accept
            </button>
        </form>

        <a href="{{ route('dashboard') }}"
           class="block text-center text-sm font-semibold text-slate-600 underline-offset-2 hover:underline dark:text-slate-300">
            Stay where I am
        </a>
    </div>

</x-layouts.guest>
