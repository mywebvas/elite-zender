{{--
    A suspended workspace is not a 403 with a shrug.

    Previously this was `abort(403, 'This workspace has been suspended.')`,
    which renders a generic denial page: no reason, no contact, no way out.
    The person reading it is a paying customer locked out of their own data,
    and the only thing they can do with a bare 403 is write an angry email to
    whatever address they can find.
--}}
<x-layouts.guest header="Workspace paused"
                 subheader="Your account is safe — access is temporarily on hold">

    <div class="space-y-5">
        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            Access to <span class="font-semibold text-slate-900 dark:text-white">{{ $workspace ?? 'this workspace' }}</span>
            has been paused by our team. This is almost always a billing or verification
            matter, and it is usually resolved the same day.
        </p>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Nothing has been deleted</p>
            <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                Your contacts, campaigns, automations, relay settings and reporting are all
                exactly as you left them. The moment the hold is lifted, everything is
                there — there is nothing to set up again.
            </p>
        </div>

        <a href="mailto:{{ $supportEmail }}?subject={{ rawurlencode('Workspace paused — '.($workspace ?? 'access request')) }}"
           class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            Email {{ $supportEmail }}
        </a>

        <p class="text-center text-xs text-slate-500 dark:text-slate-400">
            Quote your workspace name and we will find you straight away.
            <br>
            <a href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('suspended-logout').submit();"
               class="font-semibold underline underline-offset-2">Sign out</a>
        </p>

        <form id="suspended-logout" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
    </div>

</x-layouts.guest>
