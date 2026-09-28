<x-layouts.app header="Plan & billing">

    @php
        $current = $subscription?->plan;
        $statusTones = [
            'trialing' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-400',
            'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
            'past_due' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
            'canceled' => 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400',
            'expired' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
        ];
    @endphp

    {{-- Current plan --}}
    <div class="card mb-6 p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Current plan</p>
                <div class="mt-1 flex items-center gap-3">
                    <h2 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                        {{ $current?->name ?? 'No plan' }}
                    </h2>
                    @if($subscription)
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusTones[$subscription->status] ?? $statusTones['canceled'] }}">
                            {{ str_replace('_', ' ', $subscription->status) }}
                        </span>
                    @endif
                </div>

                @if($subscription?->onTrial())
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Trial ends {{ $subscription->trial_ends_at->diffForHumans() }}.
                    </p>
                @elseif($subscription?->current_period_end)
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ $subscription->canceled_at ? 'Access ends' : 'Renews' }}
                        {{ $subscription->current_period_end->toFormattedDayDateString() }}
                        @if(! $subscription->canceled_at && $subscription->amount > 0)
                            · <x-money :amount="$subscription->amount" :currency="$subscription->currency" /> / month
                        @endif
                    </p>
                @endif
            </div>

            @if($subscription && ! $subscription->canceled_at && ($current?->isFree() === false))
                <form method="POST" action="{{ route('billing.cancel') }}"
                      onsubmit="return confirm('Cancel at the end of the current period? You keep access until then.')">
                    @csrf
                    <x-button type="submit" variant="ghost">Cancel plan</x-button>
                </form>
            @endif
        </div>

        @if($subscription?->status === 'past_due')
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                    We could not collect your last payment. Sending continues for
                    {{ config('billing.grace_days') }} days — settle the open invoice below to avoid interruption.
                </p>
            </div>
        @endif
    </div>

    {{-- Usage --}}
    <div class="card mb-6 p-6">
        <h3 class="mb-4 text-base font-bold text-slate-900 dark:text-white">Usage this month</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(['contacts' => 'Contacts', 'emails_per_month' => 'Emails sent', 'smtp_accounts' => 'SMTP relays', 'users' => 'Team members'] as $key => $label)
                @php $row = $usage[$key]; @endphp
                <div>
                    <div class="mb-1.5 flex items-baseline justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</span>
                        <span class="text-xs font-semibold tabular-nums text-slate-700 dark:text-slate-300">
                            {{ number_format($row['used']) }}{{ $row['limit'] === null ? '' : ' / '.number_format($row['limit']) }}
                        </span>
                    </div>
                    @if($row['percent'] === null)
                        <div class="h-1.5 w-full rounded-full bg-emerald-100 dark:bg-emerald-500/20"></div>
                        <p class="mt-1 text-[11px] text-emerald-600 dark:text-emerald-400">Unlimited</p>
                    @else
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                            <div class="h-full rounded-full {{ $row['percent'] >= 90 ? 'bg-rose-500' : ($row['percent'] >= 70 ? 'bg-amber-500' : 'bg-indigo-500') }}"
                                 style="width: {{ $row['percent'] }}%"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">{{ $row['percent'] }}% used</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Plans --}}
    <h3 class="mb-4 text-base font-bold text-slate-900 dark:text-white">
        Plans <span class="font-normal text-slate-400">· billed in {{ $currency }}</span>
    </h3>

    <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach($plans as $plan)
            @php
                $price = $plan->priceFor($currency);
                $isCurrent = $current?->id === $plan->id;
            @endphp
            <div class="card flex flex-col p-5 {{ $isCurrent ? 'ring-2 ring-indigo-500' : '' }}">
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h4 class="font-bold text-slate-900 dark:text-white">{{ $plan->name }}</h4>
                        @if($isCurrent)
                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-400">Current</span>
                        @endif
                    </div>

                    <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">
                        @if($plan->isQuoteOnly())
                            Let's talk
                        @else
                            <x-money :amount="$price" :currency="$currency" />
                            <span class="text-sm font-medium text-slate-400">/mo</span>
                        @endif
                    </p>

                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $plan->description }}</p>

                    <ul class="mt-4 space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
                        @foreach(['contacts' => 'contacts', 'emails_per_month' => 'emails/mo', 'smtp_accounts' => 'SMTP relays', 'users' => 'team members'] as $key => $label)
                            <li class="flex items-center gap-2">
                                <svg class="h-3.5 w-3.5 flex-shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                {{ $plan->limit($key) === null ? 'Unlimited' : number_format($plan->limit($key)) }} {{ $label }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-5">
                    @if($isCurrent)
                        <x-button variant="ghost" class="w-full justify-center" disabled>Your plan</x-button>
                    @elseif($plan->isQuoteOnly())
                        <x-button href="mailto:sales@elitesender.app?subject=Enterprise plan" variant="secondary" class="w-full justify-center">Contact sales</x-button>
                    @else
                        <form method="POST" action="{{ route('billing.subscribe') }}">
                            @csrf
                            <input type="hidden" name="plan" value="{{ $plan->code }}">
                            <x-button type="submit" variant="primary" class="w-full justify-center">
                                {{ $price === 0 ? 'Switch to Free' : 'Choose '.$plan->name }}
                            </x-button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($gateways === [])
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
            <p class="text-sm text-amber-800 dark:text-amber-300">
                No payment method is configured for {{ $currency }} yet. Contact support to arrange payment.
            </p>
        </div>
    @endif

    {{-- Invoices --}}
    <div class="card overflow-hidden">
        <div class="border-b border-slate-200 px-6 py-4 dark:border-white/[0.06]">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Invoices</h3>
        </div>

        @forelse($invoices as $invoice)
            @if($loop->first)<div class="divide-y divide-slate-100 dark:divide-white/[0.04]">@endif
            <div class="flex flex-wrap items-center gap-4 px-6 py-4">
                <div class="min-w-0 flex-1">
                    <p class="font-mono text-sm font-semibold text-slate-900 dark:text-white">{{ $invoice->number }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $invoice->created_at->toFormattedDayDateString() }}
                        @if($invoice->isOverdue()) · <span class="font-semibold text-rose-500">overdue</span>@endif
                    </p>
                </div>
                <x-money :amount="$invoice->total" :currency="$invoice->currency" class="text-sm font-semibold text-slate-900 dark:text-white" />
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold
                    {{ $invoice->isPaid() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                        : ($invoice->status === 'void' ? 'bg-slate-100 text-slate-500 dark:bg-white/5' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400') }}">
                    {{ $invoice->status }}
                </span>
                <a href="{{ route('billing.invoices.show', $invoice->id) }}" class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">
                    {{ $invoice->isPayable() ? 'Pay now →' : 'View →' }}
                </a>
            </div>
            @if($loop->last)</div>@endif
        @empty
            <p class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No invoices yet.</p>
        @endforelse
    </div>
</x-layouts.app>
