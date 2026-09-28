<x-layouts.app header="Plan & billing">

    @php
        $current = $subscription?->plan;
        $pending = $subscription?->pendingPlan;

        $statusTone = [
            'trialing' => ['Trial', 'sky'],
            'active' => ['Active', 'emerald'],
            'past_due' => ['Payment due', 'amber'],
            'canceled' => ['Cancelled', 'slate'],
            'expired' => ['Lapsed', 'rose'],
        ][$subscription?->status ?? 'canceled'] ?? ['Cancelled', 'slate'];
    @endphp

    {{-- ───────────────────────── Current plan ───────────────────────── --}}
    <section class="card mb-6 overflow-hidden">
        <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <p class="stat-label">Your plan</p>

                <div class="mt-1.5 flex flex-wrap items-center gap-3">
                    <h2 class="text-3xl font-bold tracking-[-0.035em] text-slate-900 dark:text-white">
                        {{ $current?->name ?? 'No plan yet' }}
                    </h2>
                    <x-badge :variant="$statusTone[1]">{{ $statusTone[0] }}</x-badge>
                </div>

                <p class="mt-2 max-w-prose text-sm text-slate-500 dark:text-slate-400">
                    @if($subscription?->onTrial())
                        Your trial runs until {{ $subscription->trial_ends_at->toFormattedDayDateString() }}
                        ({{ $subscription->trial_ends_at->diffForHumans() }}). Nothing is charged until then.
                    @elseif($subscription?->isEnding())
                        You have access to everything until {{ $subscription->cancel_at?->toFormattedDayDateString() }}.
                        After that you move to the Free plan — your contacts, campaigns and history all stay put.
                    @elseif($subscription?->status === 'expired')
                        Sending is paused until the open invoice is settled. Everything else is exactly where you left it.
                    @elseif($subscription?->current_period_end)
                        Renews {{ $subscription->current_period_end->toFormattedDayDateString() }}
                        @if($subscription->amount > 0)
                            for <x-money :amount="$subscription->amount" :currency="$subscription->currency" class="font-semibold text-slate-700 dark:text-slate-200" />
                        @endif
                        @if($card) · {{ $card->label() }} @endif
                    @else
                        Pick a plan below to get started.
                    @endif
                </p>

                @if($pending)
                    <p class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-400">
                        Scheduled: moving to {{ $pending->name }} on {{ $subscription->current_period_end?->toFormattedDayDateString() }}.
                    </p>
                @endif
            </div>

            <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
                @if($subscription?->isEnding())
                    <form method="POST" action="{{ route('billing.resume') }}">
                        @csrf
                        <x-button type="submit" variant="primary">Keep my plan</x-button>
                    </form>
                @elseif($pending)
                    <form method="POST" action="{{ route('billing.keep-plan') }}">
                        @csrf
                        <x-button type="submit" variant="secondary">Cancel scheduled change</x-button>
                    </form>
                @elseif($subscription && $current && ! $current->isFree())
                    <form method="POST" action="{{ route('billing.cancel') }}"
                          onsubmit="return confirm('Cancel at the end of this period? You keep everything until then, and you can undo it any time.')">
                        @csrf
                        <x-button type="submit" variant="ghost">Cancel plan</x-button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Payment method on file --}}
        @if($card)
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-6 py-3.5 dark:border-white/[0.06] dark:bg-white/[0.02]">
                <div class="flex items-center gap-2.5 text-sm text-slate-600 dark:text-slate-300">
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <span><span class="font-medium">{{ $card->label() }}</span> — renewals happen automatically.</span>
                </div>
                <form method="POST" action="{{ route('billing.card.forget') }}"
                      onsubmit="return confirm('Remove this card? We will email you an invoice before each renewal instead.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs font-semibold text-slate-500 underline-offset-2 hover:text-rose-600 hover:underline dark:hover:text-rose-400">
                        Remove card
                    </button>
                </form>
            </div>
        @endif
    </section>

    {{-- ───────────────────────── Usage ───────────────────────── --}}
    <section class="card mb-6 p-6">
        <div class="mb-5 flex items-baseline justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Usage this month</h3>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    Your sending allowance resets on {{ now()->endOfMonth()->addDay()->toFormattedDayDateString() }}.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(['contacts' => 'Contacts', 'emails_per_month' => 'Emails sent', 'smtp_accounts' => 'SMTP relays', 'users' => 'Team members'] as $key => $label)
                @php $row = $usage[$key]; @endphp
                <div>
                    <div class="mb-2 flex items-baseline justify-between gap-2">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ $label }}</span>
                        <span class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
                            {{ number_format($row['used']) }}<span class="text-slate-400">{{ $row['limit'] === null ? '' : ' / '.number_format($row['limit']) }}</span>
                        </span>
                    </div>

                    @if($row['percent'] === null)
                        <div class="h-1.5 w-full rounded-full bg-emerald-500/20"></div>
                        <p class="mt-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">Unlimited</p>
                    @else
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                            <div class="h-full rounded-full transition-[width] duration-700 ease-[cubic-bezier(0.16,1,0.3,1)] {{ $row['percent'] >= 90 ? 'bg-rose-500' : ($row['percent'] >= 70 ? 'bg-amber-500' : 'bg-indigo-500') }}"
                                 style="width: {{ max(2, $row['percent']) }}%"></div>
                        </div>
                        <p class="mt-1.5 text-xs {{ $row['percent'] >= 90 ? 'font-medium text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
                            {{ $row['percent'] >= 90 ? 'Nearly full — consider upgrading' : $row['percent'].'% used' }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- ───────────────────────── Plans ───────────────────────── --}}
    <section class="mb-8">
        <div class="mb-5 flex flex-wrap items-baseline justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Choose your plan</h3>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    Upgrade instantly and pay only for the rest of this period. Downgrades take effect at your next renewal, so you never lose what you have paid for.
                </p>
            </div>
            <x-badge variant="slate">Billed in {{ $currency }}</x-badge>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            @foreach($plans as $plan)
                @php
                    $price = $plan->priceFor($currency);
                    $isCurrent = $current?->id === $plan->id;
                    $isPending = $pending?->id === $plan->id;
                    $isUpgrade = $subscription !== null && $price !== null && $price > $subscription->amount;
                    $featured = $plan->code === 'growth';
                @endphp

                <div @class([
                    'relative flex flex-col p-5',
                    'card' => ! $isCurrent,
                    'card-elevated ring-2 ring-indigo-500' => $isCurrent,
                ])>
                    @if($featured && ! $isCurrent)
                        <span class="absolute -top-2.5 left-5 rounded-full bg-gradient-to-r from-indigo-600 to-violet-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-sm">
                            Most popular
                        </span>
                    @endif

                    <div class="flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <h4 class="font-semibold text-slate-900 dark:text-white">{{ $plan->name }}</h4>
                            @if($isCurrent)
                                <x-badge variant="indigo">Current</x-badge>
                            @elseif($isPending)
                                <x-badge variant="amber">Scheduled</x-badge>
                            @endif
                        </div>

                        <p class="mt-3 flex items-baseline gap-1">
                            @if($plan->isQuoteOnly())
                                <span class="text-2xl font-bold tracking-[-0.035em] text-slate-900 dark:text-white">Let's talk</span>
                            @else
                                <x-money :amount="$price" :currency="$currency"
                                         class="text-3xl font-bold tracking-[-0.035em] text-slate-900 dark:text-white" />
                                <span class="text-sm font-medium text-slate-400">/month</span>
                            @endif
                        </p>

                        <p class="mt-2 min-h-[2.5rem] text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ $plan->description }}
                        </p>

                        <ul class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-xs dark:border-white/[0.06]">
                            @foreach(['contacts' => 'contacts', 'emails_per_month' => 'emails a month', 'smtp_accounts' => 'sending relays', 'users' => 'team members'] as $key => $label)
                                <li class="flex items-start gap-2 text-slate-600 dark:text-slate-300">
                                    <svg class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span>
                                        <span class="font-medium text-slate-900 dark:text-white">
                                            {{ $plan->limit($key) === null ? 'Unlimited' : number_format($plan->limit($key)) }}
                                        </span>
                                        {{ $label }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="mt-5">
                        @if($isCurrent)
                            <x-button variant="secondary" class="w-full justify-center" disabled>Your current plan</x-button>
                        @elseif($plan->isQuoteOnly())
                            <x-button href="mailto:sales@elitesender.app?subject=Enterprise%20plan" variant="secondary" class="w-full justify-center">
                                Talk to sales
                            </x-button>
                        @else
                            <form method="POST" action="{{ route('billing.subscribe') }}">
                                @csrf
                                <input type="hidden" name="plan" value="{{ $plan->code }}">
                                <x-button type="submit" :variant="$featured ? 'gradient' : 'secondary'" class="w-full justify-center">
                                    @if($subscription === null || $price === 0)
                                        {{ $price === 0 ? 'Switch to Free' : 'Choose '.$plan->name }}
                                    @elseif($isUpgrade)
                                        Upgrade now
                                    @else
                                        Switch at renewal
                                    @endif
                                </x-button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if($gateways === [])
            <x-banner tone="warning" class="mt-4" title="No payment method is set up for {{ $currency }}">
                Get in touch and we will arrange an alternative — you will not lose access in the meantime.
            </x-banner>
        @endif
    </section>

    {{-- ───────────────────────── Invoices ───────────────────────── --}}
    <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-white/[0.06]">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Invoices</h3>
            <span class="text-xs text-slate-400">{{ $invoices->count() }} shown</span>
        </div>

        @forelse($invoices as $invoice)
            @if($loop->first)<ul class="divide-y divide-slate-100 dark:divide-white/[0.04]">@endif
            <li class="flex flex-wrap items-center gap-4 px-6 py-4 transition-colors hover:bg-slate-50/70 dark:hover:bg-white/[0.02]">
                <div class="min-w-0 flex-1">
                    <p class="font-mono text-sm font-semibold text-slate-900 dark:text-white">{{ $invoice->number }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $invoice->created_at->toFormattedDayDateString() }}
                        @if($invoice->reason === 'renewal') · renewal
                        @elseif($invoice->reason === 'upgrade') · upgrade
                        @endif
                        @if($invoice->isOverdue())
                            · <span class="font-semibold text-rose-600 dark:text-rose-400">overdue</span>
                        @endif
                    </p>
                </div>

                <x-money :amount="$invoice->total" :currency="$invoice->currency"
                         class="text-sm font-semibold text-slate-900 dark:text-white" />

                <x-badge :variant="$invoice->isPaid() ? 'emerald' : ($invoice->status === 'void' ? 'slate' : 'amber')">
                    {{ ucfirst($invoice->status) }}
                </x-badge>

                <a href="{{ route('billing.invoices.show', $invoice->id) }}"
                   class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                    {{ $invoice->isPayable() ? 'Pay now →' : 'View →' }}
                </a>
            </li>
            @if($loop->last)</ul>@endif
        @empty
            <div class="px-6 py-12 text-center">
                <p class="text-sm font-medium text-slate-900 dark:text-white">No invoices yet</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">They will appear here as soon as you move to a paid plan.</p>
            </div>
        @endforelse
    </section>
</x-layouts.app>
