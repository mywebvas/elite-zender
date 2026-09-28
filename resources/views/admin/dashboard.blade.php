<x-layouts.admin title="Overview">

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-6">
        @foreach([
            'Workspaces' => $stats['tenants'], 'Active' => $stats['active_tenants'],
            'Users' => $stats['users'], 'Contacts' => $stats['contacts'],
            'Campaigns' => $stats['campaigns'], 'Emails sent' => $stats['emails_sent'],
        ] as $label => $value)
            <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-4">
                <p class="text-xs font-medium text-slate-400">{{ $label }}</p>
                <p class="mt-1 text-2xl font-black tabular-nums">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Revenue is never summed across currencies: adding naira to dollars
             produces a number that is wrong in both. --}}
        <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
            <h2 class="mb-3 text-sm font-semibold">Collected revenue</h2>
            @forelse($revenue as $currency => $totals)
                <div class="mb-3 last:mb-0">
                    <p class="text-xs text-slate-400">{{ $currency }} this month</p>
                    <p class="text-xl font-bold tabular-nums"><x-money :amount="$totals['this_month']" :currency="$currency" /></p>
                    <p class="text-xs text-slate-500">All time: <x-money :amount="$totals['all_time']" :currency="$currency" /></p>
                </div>
            @empty
                <p class="text-sm text-slate-500">No payments recorded yet.</p>
            @endforelse
        </div>

        <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
            <h2 class="mb-3 text-sm font-semibold">Subscriptions</h2>
            @forelse($subscriptions as $status => $count)
                <div class="flex items-center justify-between py-1 text-sm">
                    <span class="text-slate-400">{{ str_replace('_', ' ', $status) }}</span>
                    <span class="font-semibold tabular-nums">{{ number_format($count) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">None yet.</p>
            @endforelse
        </div>

        <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
            <h2 class="mb-3 text-sm font-semibold">By plan</h2>
            @forelse($planBreakdown as $plan => $count)
                <div class="flex items-center justify-between py-1 text-sm">
                    <span class="text-slate-400">{{ $plan }}</span>
                    <span class="font-semibold tabular-nums">{{ number_format($count) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">None yet.</p>
            @endforelse
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-xl border border-white/10 bg-[#0b0b14]">
            <div class="flex items-center justify-between border-b border-white/10 px-5 py-3">
                <h2 class="text-sm font-semibold">Bank transfers awaiting review</h2>
                <a href="{{ route('admin.invoices.index') }}" class="text-xs text-amber-500 hover:underline">All billing →</a>
            </div>
            @forelse($awaitingReview as $payment)
                <div class="flex items-center justify-between border-b border-white/5 px-5 py-3 text-sm last:border-0">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $payment->tenant?->name ?? 'Unknown workspace' }}</p>
                        <p class="font-mono text-xs text-slate-500">{{ $payment->invoice?->number }} · {{ $payment->reference }}</p>
                    </div>
                    <x-money :amount="$payment->amount" :currency="$payment->currency" class="font-semibold" />
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-500">Nothing waiting.</p>
            @endforelse
        </div>

        <div class="rounded-xl border border-white/10 bg-[#0b0b14]">
            <div class="border-b border-white/10 px-5 py-3">
                <h2 class="text-sm font-semibold">Overdue invoices</h2>
            </div>
            @forelse($overdue as $invoice)
                <div class="flex items-center justify-between border-b border-white/5 px-5 py-3 text-sm last:border-0">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $invoice->tenant?->name }}</p>
                        <p class="font-mono text-xs text-rose-400">{{ $invoice->number }} · {{ $invoice->due_at?->diffForHumans() }}</p>
                    </div>
                    <x-money :amount="$invoice->balance()" :currency="$invoice->currency" class="font-semibold" />
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-500">Nothing overdue.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-white/10 bg-[#0b0b14]">
        <div class="border-b border-white/10 px-5 py-3">
            <h2 class="text-sm font-semibold">Newest workspaces</h2>
        </div>
        @foreach($newestTenants as $tenant)
            <a href="{{ route('admin.tenants.show', $tenant->id) }}"
               class="flex items-center justify-between border-b border-white/5 px-5 py-3 text-sm transition last:border-0 hover:bg-white/5">
                <div>
                    <p class="font-medium">{{ $tenant->name }}</p>
                    <p class="text-xs text-slate-500">{{ $tenant->users_count }} user(s) · {{ $tenant->created_at->diffForHumans() }}</p>
                </div>
                <span class="rounded-full px-2 py-0.5 text-xs {{ $tenant->status === 'active' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">
                    {{ $tenant->status }}
                </span>
            </a>
        @endforeach
    </div>
</x-layouts.admin>
