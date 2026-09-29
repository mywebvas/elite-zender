<x-layouts.app title="Confirm your email">

    <div class="mx-auto max-w-2xl">
        @if (session('status') === 'verification-link-sent')
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10"
                 role="status">
                <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300">
                    A new link is on its way to {{ auth()->user()->email }}. It can take a minute or two to arrive.
                </p>
            </div>
        @endif

        <section class="card p-8">
            <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                </svg>
            </div>

            <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-white">
                Confirm your email to start sending
            </h2>

            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                We sent a link to <span class="font-semibold text-slate-900 dark:text-white">{{ auth()->user()->email }}</span>.
                Click it and you are done — it takes a few seconds.
            </p>

            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-600 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-300">
                <p class="font-semibold text-slate-900 dark:text-white">Why we ask</p>
                <p class="mt-1">
                    We send email on your behalf. Confirming that you own this address is what keeps
                    our sending reputation — and therefore your deliverability — intact.
                </p>
                <p class="mt-2">
                    <span class="font-semibold text-slate-900 dark:text-white">Everything else stays open.</span>
                    Connect a relay, import contacts, build a campaign. Only the send button waits.
                </p>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <x-button type="submit" variant="primary">Send the link again</x-button>
                </form>

                <a href="{{ route('onboarding') }}"
                   class="text-sm font-semibold text-slate-600 underline-offset-2 hover:underline dark:text-slate-300">
                    Carry on setting up &rarr;
                </a>
            </div>

            <p class="mt-6 border-t border-slate-100 pt-5 text-xs text-slate-500 dark:border-white/[0.06] dark:text-slate-400">
                Wrong address? Change it in <a href="{{ route('settings.index') }}" class="font-semibold underline underline-offset-2">Settings</a> and we will send a fresh link.
                Still nothing after a few minutes, check spam or write to
                <a href="mailto:{{ config('platform.support_email') }}" class="font-semibold underline underline-offset-2">{{ config('platform.support_email') }}</a>.
            </p>
        </section>
    </div>

</x-layouts.app>
