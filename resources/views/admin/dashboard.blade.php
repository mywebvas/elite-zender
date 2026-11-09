<x-layouts.admin title="Platform Overview" subtitle="High-level operational metrics and real-time alerts.">

    {{-- ─────────────────── Critical Attention Center ─────────────────── --}}
    @php $needsAttention = array_filter($attention); @endphp
    @if($needsAttention)
        <section class="mb-8 overflow-hidden rounded-2xl border border-amber-500/35 bg-gradient-to-r from-amber-500/[0.12] via-amber-500/[0.06] to-transparent p-5 shadow-lg shadow-amber-950/20" aria-label="Actionable alerts">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-amber-500/20 text-amber-400 ring-1 ring-amber-500/30">
                    <svg class="h-5 w-5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-extrabold uppercase tracking-wider text-amber-300">Needs attention</h2>
                        <span class="rounded-full bg-amber-400/20 px-2 py-0.5 text-[10px] font-bold text-amber-300">{{ count($needsAttention) }} issue(s)</span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @if($attention['failed_jobs'])
                            <a href="{{ route('admin.system.index') }}"
                               class="inline-flex items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-3.5 py-2 text-xs font-bold text-amber-200 transition hover:bg-amber-500/20 hover:text-white shadow-sm">
                                <span class="h-2 w-2 rounded-full bg-rose-400 animate-ping"></span>
                                <span>{{ $attention['failed_jobs'] }} failed job(s)</span>
                                <span class="text-amber-400 font-mono">Inspect →</span>
                            </a>
                        @endif
                        @if($attention['paused_automations'])
                            <a href="{{ route('admin.system.index') }}"
                               class="inline-flex items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-3.5 py-2 text-xs font-bold text-amber-200 transition hover:bg-amber-500/20 hover:text-white shadow-sm">
                                <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                                <span>{{ $attention['paused_automations'] }} paused automation enrolment(s)</span>
                                <span class="text-amber-400 font-mono">View →</span>
                            </a>
                        @endif
                        @if($attention['suspended_tenants'])
                            <a href="{{ route('admin.tenants.index', ['status' => 'suspended']) }}"
                               class="inline-flex items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-3.5 py-2 text-xs font-bold text-amber-200 transition hover:bg-amber-500/20 hover:text-white shadow-sm">
                                <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                                <span>{{ $attention['suspended_tenants'] }} suspended workspace(s)</span>
                                <span class="text-amber-400 font-mono">Review →</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ─────────────────── Platform KPIs ─────────────────── --}}
    <section class="mb-8" aria-label="Key Performance Indicators">
        <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-6">
            @foreach([
                ['label' => 'Total Workspaces', 'value' => $stats['tenants'], 'meta' => number_format($stats['active_tenants']).' active', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16'],
                ['label' => 'Customer Users', 'value' => $stats['users'], 'meta' => 'Across tenants', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857'],
                ['label' => 'Total Contacts', 'value' => $stats['contacts'], 'meta' => 'Live subscribers', 'icon' => 'M12 4.354a4 4 0 110 5.292'],
                ['label' => 'Campaigns Built', 'value' => $stats['campaigns'], 'meta' => 'All workspaces', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8'],
                ['label' => 'Emails Dispatched', 'value' => $stats['emails_sent'], 'meta' => 'Total outbound', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                ['label' => 'Active Tenants', 'value' => $stats['active_tenants'], 'meta' => 'In good standing', 'icon' => 'M9 12l2 2 4-4'],
            ] as $kpi)
                <div class="relative overflow-hidden rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-4.5 shadow-sm transition hover:border-amber-500/30">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $kpi['label'] }}</span>
                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $kpi['icon'] }}"/>
                        </svg>
                    </div>
                    <p class="mt-2.5 text-2xl font-black tabular-nums tracking-tight text-white">{{ number_format($kpi['value']) }}</p>
                    <p class="mt-1 text-[11px] font-medium text-amber-400/80">{{ $kpi['meta'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ─────────────────── Revenue & Subscriptions Matrix ─────────────────── --}}
    <section class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3" aria-label="Financials and Plans">
        {{-- Revenue Stream --}}
        <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-5 shadow-sm">
            <div class="flex items-center justify-between border-b border-white/[0.06] pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-400 text-xs font-bold">$</span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-300">Collected revenue</h2>
                </div>
                <span class="text-[10px] font-bold uppercase text-slate-500 tracking-wider">Multi-Currency</span>
            </div>

            <div class="space-y-4">
                @forelse($revenue as $currency => $totals)
                    <div class="rounded-xl border border-white/[0.05] bg-white/[0.02] p-3.5">
                        <div class="flex items-center justify-between">
                            <span class="rounded bg-white/[0.06] px-2 py-0.5 font-mono text-[11px] font-bold text-amber-300">{{ $currency }}</span>
                            <span class="text-[11px] text-slate-400">This month</span>
                        </div>
                        <p class="mt-2 text-xl font-black tabular-nums text-white">
                            <x-money :amount="$totals['this_month']" :currency="$currency" />
                        </p>
                        <div class="mt-2 flex items-center justify-between border-t border-white/[0.04] pt-2 text-xs text-slate-400">
                            <span>All-time collected</span>
                            <span class="font-bold tabular-nums text-slate-300">
                                <x-money :amount="$totals['all_time']" :currency="$currency" />
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-slate-500">
                        <p>No settled payments recorded yet.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Subscriptions State --}}
        <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-5 shadow-sm">
            <div class="flex items-center justify-between border-b border-white/[0.06] pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-500/15 text-indigo-400 text-xs font-bold">#</span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-300">Subscriptions State</h2>
                </div>
                <span class="text-[10px] font-bold uppercase text-slate-500 tracking-wider">{{ (is_object($subscriptions) && method_exists($subscriptions, 'sum')) ? $subscriptions->sum() : (is_array($subscriptions) ? array_sum($subscriptions) : 0) }} Total</span>
            </div>

            <div class="space-y-2.5">
                @forelse($subscriptions as $status => $count)
                    <div class="flex items-center justify-between rounded-xl border border-white/[0.04] bg-white/[0.02] px-3.5 py-2.5 text-xs">
                        <div class="flex items-center gap-2">
                            <span @class([
                                'h-2 w-2 rounded-full',
                                'bg-emerald-400' => $status === 'active',
                                'bg-indigo-400' => $status === 'trialing',
                                'bg-amber-400' => $status === 'past_due',
                                'bg-rose-400' => in_array($status, ['expired', 'cancelled'], true),
                            ])></span>
                            <span class="font-semibold capitalize text-slate-300">{{ str_replace('_', ' ', $status) }}</span>
                        </div>
                        <span class="font-bold tabular-nums text-white">{{ number_format($count) }}</span>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-slate-500">
                        <p>No subscriptions recorded.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Customers by Plan --}}
        <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-5 shadow-sm">
            <div class="flex items-center justify-between border-b border-white/[0.06] pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-500/15 text-amber-400 text-xs font-bold">★</span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-300">Plan Adoption</h2>
                </div>
                <a href="{{ route('admin.plans.index') }}" class="text-[11px] font-bold text-amber-400 hover:underline">Manage Plans →</a>
            </div>

            <div class="space-y-2.5">
                @forelse($planBreakdown as $plan => $count)
                    <div class="flex items-center justify-between rounded-xl border border-white/[0.04] bg-white/[0.02] px-3.5 py-2.5 text-xs">
                        <span class="font-bold text-white">{{ $plan }}</span>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-extrabold text-amber-300 tabular-nums">{{ number_format($count) }}</span>
                            <span class="text-[10px] text-slate-500">tenant(s)</span>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-slate-500">
                        <p>No active plan assignments.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ─────────────────── Actionable Queues ─────────────────── --}}
    <section class="grid grid-cols-1 gap-6 xl:grid-cols-2" aria-label="Pending Approvals and Overdues">
        {{-- Bank Transfers Awaiting Review --}}
        <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] shadow-sm">
            <div class="flex items-center justify-between border-b border-white/[0.08] px-5 py-4">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-white">Bank Transfers Awaiting Review</h2>
                </div>
                <a href="{{ route('admin.invoices.index') }}" class="text-xs font-bold text-amber-400 hover:text-amber-300">
                    All Billing →
                </a>
            </div>

            <div class="divide-y divide-white/[0.04]">
                @forelse($awaitingReview as $payment)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4 transition hover:bg-white/[0.02]">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-sm text-white">{{ $payment->tenant?->name ?? 'Unknown workspace' }}</p>
                            <p class="font-mono text-xs text-slate-400 mt-0.5">
                                {{ $payment->invoice?->number }} · Ref: <span class="text-amber-300">{{ $payment->reference }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-black text-sm text-emerald-400 tabular-nums">
                                <x-money :amount="$payment->amount" :currency="$payment->currency" />
                            </span>
                            <a href="{{ route('admin.invoices.index') }}"
                               class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-1.5 text-xs font-bold text-amber-300 hover:bg-amber-500/20 transition">
                                Review
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-xs text-slate-500">
                        <svg class="mx-auto h-8 w-8 text-slate-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="font-medium text-slate-400">All offline transfers settled</p>
                        <p class="mt-0.5">No manual bank deposits waiting for operator verification.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Overdue Invoices --}}
        <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] shadow-sm">
            <div class="flex items-center justify-between border-b border-white/[0.08] px-5 py-4">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-white">Overdue Invoices</h2>
                </div>
                <a href="{{ route('admin.invoices.index', ['status' => 'open']) }}" class="text-xs font-bold text-amber-400 hover:text-amber-300">
                    Open Invoices →
                </a>
            </div>

            <div class="divide-y divide-white/[0.04]">
                @forelse($overdue as $invoice)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4 transition hover:bg-white/[0.02]">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-sm text-white">{{ $invoice->tenant?->name ?? 'Unknown workspace' }}</p>
                            <p class="font-mono text-xs text-rose-400 mt-0.5">
                                {{ $invoice->number }} · Due {{ $invoice->due_at?->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-black text-sm text-white tabular-nums">
                                <x-money :amount="$invoice->balance()" :currency="$invoice->currency" />
                            </span>
                            <a href="{{ route('admin.invoices.index', ['search' => $invoice->number]) }}"
                               class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-1.5 text-xs font-bold text-slate-300 hover:bg-white/[0.08] hover:text-white transition">
                                Inspect
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-xs text-slate-500">
                        <svg class="mx-auto h-8 w-8 text-slate-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="font-medium text-slate-400">Zero overdue invoices</p>
                        <p class="mt-0.5">Every active workspace invoice is paid or within terms.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ─────────────────── Activity & Workspaces ─────────────────── --}}
    <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-2" aria-label="Audit Activity and Workspaces">
        {{-- Recent Operator Activity --}}
        <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] shadow-sm">
            <div class="flex items-center justify-between border-b border-white/[0.08] px-5 py-4">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-white">Recent operator activity</h2>
                </div>
                <a href="{{ route('admin.activity.index') }}" class="text-xs font-bold text-amber-400 hover:text-amber-300">
                    Full Log →
                </a>
            </div>

            <div class="divide-y divide-white/[0.04]">
                @forelse($recentActivity as $entry)
                    <div class="flex items-start gap-3 p-4 transition hover:bg-white/[0.02]">
                        <span @class([
                            'mt-1.5 h-2 w-2 flex-shrink-0 rounded-full',
                            'bg-rose-400 shadow-[0_0_6px_rgba(244,63,94,0.6)]' => $entry->severity === 'critical',
                            'bg-amber-400 shadow-[0_0_6px_rgba(245,158,11,0.6)]' => $entry->severity === 'notice',
                            'bg-slate-500' => $entry->severity === 'info',
                        ])></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-white">{{ $entry->description }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                <span class="font-medium text-slate-300">{{ $entry->admin_email }}</span>
                                · {{ $entry->created_at?->diffForHumans() }}
                                @if($entry->tenant)
                                    · <span class="text-amber-400">{{ $entry->tenant->name }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-xs text-slate-500">
                        <p>No operator actions recorded yet.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Newest Workspaces --}}
        <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] shadow-sm">
            <div class="flex items-center justify-between border-b border-white/[0.08] px-5 py-4">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-white">Recently Created Workspaces</h2>
                </div>
                <a href="{{ route('admin.tenants.index') }}" class="text-xs font-bold text-amber-400 hover:text-amber-300">
                    All Workspaces →
                </a>
            </div>

            <div class="divide-y divide-white/[0.04]">
                @foreach($newestTenants as $tenant)
                    <a href="{{ route('admin.tenants.show', $tenant->id) }}"
                       class="flex items-center justify-between p-4 transition hover:bg-white/[0.03]">
                        <div class="min-w-0">
                            <p class="font-bold text-sm text-white">{{ $tenant->name }}</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                {{ $tenant->users_count }} member(s) · Created {{ $tenant->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                'bg-emerald-500/15 text-emerald-400 border border-emerald-500/25' => $tenant->status === 'active',
                                'bg-rose-500/15 text-rose-400 border border-rose-500/25' => $tenant->status !== 'active',
                            ])>
                                {{ $tenant->status }}
                            </span>
                            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

</x-layouts.admin>
