{{--
    First-run checklist.

    What used to be here was a three-step Alpine "wizard" that persisted
    nothing at all. `testSmtp()` waited a second and toasted "SMTP Connected
    Successfully"; `importContacts()` waited 1.5 seconds and toasted
    "Contacts imported"; neither made a single request. A new customer was
    congratulated three times and arrived at a dashboard with no relay, no
    contacts and no ability to send — which is the worst possible first
    impression and, on the numbers, the most expensive bug in the product.

    This is the honest version: every tick is computed from real workspace
    state by App\Services\ActivationChecklist, and every action is a link to
    the real screen that already does the job properly, with its own
    validation. Duplicating those forms here would just create a second place
    for them to drift.
--}}
<x-layouts.app header="Get set up" title="Get set up">

    <div class="mx-auto max-w-3xl">

        {{-- Progress --}}
        <div class="mb-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                        @if($complete)
                            You are all set
                        @else
                            Let's get your first campaign out
                        @endif
                    </h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        @if($complete)
                            Every step is done. This page will stay here if you ever want to re-check something.
                        @else
                            {{ $completed }} of {{ $total }} done — about four minutes of work left.
                        @endif
                    </p>
                </div>
                <span class="flex-shrink-0 text-2xl font-black tabular-nums text-brand-600 dark:text-brand-400">{{ $percent }}%</span>
            </div>

            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10"
                 role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="Setup progress">
                <div class="h-full rounded-full bg-brand-600 transition-all duration-500 dark:bg-brand-500"
                     style="width: {{ $percent }}%"></div>
            </div>
        </div>

        {{-- Steps --}}
        <ol class="card divide-y divide-slate-100 overflow-hidden dark:divide-white/[0.06]">
            @foreach($steps as $index => $step)
                @php $isNext = ! $step['done'] && ($next['key'] ?? null) === $step['key']; @endphp

                <li class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center {{ $isNext ? 'bg-brand-50/50 dark:bg-brand-500/[0.06]' : '' }}">
                    <div class="flex min-w-0 flex-1 items-start gap-4">
                        {{-- Tick / number --}}
                        <div @class([
                            'mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold',
                            'bg-emerald-600 text-white' => $step['done'],
                            'bg-brand-600 text-white' => ! $step['done'] && $isNext,
                            'bg-slate-100 text-slate-400 dark:bg-white/[0.06] dark:text-slate-500' => ! $step['done'] && ! $isNext,
                        ])>
                            @if($step['done'])
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="sr-only">Done:</span>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </div>

                        <div class="min-w-0">
                            <p @class([
                                'text-sm font-semibold',
                                'text-slate-400 line-through dark:text-slate-500' => $step['done'],
                                'text-slate-900 dark:text-white' => ! $step['done'],
                            ])>{{ $step['label'] }}</p>

                            @unless($step['done'])
                                <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $step['help'] }}</p>
                            @endunless
                        </div>
                    </div>

                    @unless($step['done'])
                        <div class="flex-shrink-0 sm:pl-4">
                            <a href="{{ $step['url'] }}"
                               @class([
                                   'inline-flex w-full items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold transition sm:w-auto',
                                   'bg-brand-600 text-white shadow-sm hover:bg-brand-700' => $isNext,
                                   'text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 dark:text-slate-300 dark:ring-white/10 dark:hover:bg-white/[0.06]' => ! $isNext,
                               ])>
                                {{ $step['cta'] }}
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    @endunless
                </li>
            @endforeach
        </ol>

        @if($complete)
            <div class="mt-6 flex flex-col items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-500/20 dark:bg-emerald-500/10 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-medium text-emerald-900 dark:text-emerald-200">
                    Setup is complete. Reporting starts filling in as soon as recipients open and click.
                </p>
                <a href="{{ route('dashboard') }}"
                   class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                    Go to the dashboard
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        @else
            <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
                Stuck on any of these? Reply to your welcome email or write to
                <a href="mailto:{{ config('platform.support_email') }}" class="font-semibold text-brand-600 underline-offset-2 hover:underline dark:text-brand-400">{{ config('platform.support_email') }}</a>
                — a person reads it.
            </p>
        @endif

    </div>

</x-layouts.app>
