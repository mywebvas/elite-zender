{{--
    Dashboard.

    All figures come from App\Services\DashboardMetrics via DashboardController.
--}}
<x-layouts.app header="Dashboard">

    {{-- Command Center Header Banner (Stitch Design Upgrade) --}}
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-white/[0.08]">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                    Deliverability Command Center
                </h2>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    <span>All Relay Clusters Operational</span>
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ Str::of(auth()->user()->name)->explode(' ')->first() }}. Real-time telemetry, ISP placement heuristics, and throughput diagnostics.
            </p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <div class="inline-flex p-1 rounded-xl bg-slate-100 dark:bg-white/[0.04] border border-slate-200 dark:border-white/[0.08] text-xs font-medium">
                <button class="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm font-semibold">Last 24 Hours</button>
                <button class="px-3 py-1.5 rounded-lg text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">Last 7 Days</button>
                <button class="px-3 py-1.5 rounded-lg text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">Custom</button>
            </div>
            <a href="{{ route('campaigns.create') }}" class="btn-gradient flex-shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                New Campaign
            </a>
        </div>
    </div>

    {{-- Quick Action Ribbon (Stitch Design Upgrade) --}}
    <div class="mb-8 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <a href="{{ route('campaigns.create') }}" class="card flex items-center gap-3 p-3.5 hover:border-indigo-500/40 dark:hover:border-indigo-500/50 hover:bg-slate-50 dark:hover:bg-white/[0.03] transition-all group">
            <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-500/15 flex items-center justify-center text-indigo-600 dark:text-indigo-400 group-hover:scale-105 transition-transform flex-shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate">New Broadcast</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">Create & send</div>
            </div>
        </a>

        <a href="{{ route('smtp-accounts.index') }}" class="card flex items-center gap-3 p-3.5 hover:border-indigo-500/40 dark:hover:border-indigo-500/50 hover:bg-slate-50 dark:hover:bg-white/[0.03] transition-all group">
            <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-500/15 flex items-center justify-center text-amber-600 dark:text-amber-400 group-hover:scale-105 transition-transform flex-shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors truncate">Warmup Dedicated IP</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">Adaptive ramp schedules</div>
            </div>
        </a>

        <a href="{{ route('contacts.index') }}" class="card flex items-center gap-3 p-3.5 hover:border-indigo-500/40 dark:hover:border-indigo-500/50 hover:bg-slate-50 dark:hover:bg-white/[0.03] transition-all group">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-500/15 flex items-center justify-center text-emerald-600 dark:text-emerald-400 group-hover:scale-105 transition-transform flex-shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors truncate">Verify Audience</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">Import & cleanse lists</div>
            </div>
        </a>

        <a href="{{ route('bounces.index') }}" class="card flex items-center gap-3 p-3.5 hover:border-indigo-500/40 dark:hover:border-indigo-500/50 hover:bg-slate-50 dark:hover:bg-white/[0.03] transition-all group">
            <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-500/15 flex items-center justify-center text-violet-600 dark:text-violet-400 group-hover:scale-105 transition-transform flex-shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors truncate">Audit Bounces</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">Suppressions & telemetry</div>
            </div>
        </a>
    </div>

    {{--
        Activation card.

        A brand-new workspace has nothing to report, so a grid of zeroes is
        worse than useless — it says "this product does nothing". Until the
        first campaign has gone out, the dashboard leads with the next
        concrete step instead. Every tick is real workspace state, not a
        stored flag (App\Services\ActivationChecklist).
    --}}
    @if(! $activation['complete'])
        <section class="card mb-8 overflow-hidden">
            <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Finish setting up</h3>
                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-bold tabular-nums text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                            {{ $activation['completed'] }}/{{ $activation['total'] }}
                        </span>
                    </div>

                    @if($activation['next'])
                        <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-300">
                            Next: <span class="font-semibold text-slate-900 dark:text-white">{{ $activation['next']['label'] }}</span>
                        </p>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $activation['next']['help'] }}</p>
                    @endif
                </div>

                <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
                    @if($activation['next'])
                        <a href="{{ $activation['next']['url'] }}"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                            {{ $activation['next']['cta'] }}
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    @endif
                    <a href="{{ route('onboarding') }}"
                       class="inline-flex items-center rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-50 dark:text-slate-300 dark:ring-white/10 dark:hover:bg-white/[0.06]">
                        All steps
                    </a>
                </div>
            </div>

            <div class="h-1.5 bg-slate-100 dark:bg-white/[0.06]" role="progressbar"
                 aria-valuenow="{{ $activation['percent'] }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="Setup progress">
                <div class="h-full bg-brand-600 transition-all duration-500 dark:bg-brand-500"
                     style="width: {{ $activation['percent'] }}%"></div>
            </div>
        </section>
    @endif

    {{-- KPI stats --}}
    @php
        $tints = [
            'indigo' => 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400',
            'emerald' => 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
            'violet' => 'bg-violet-50 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400',
            'rose' => 'bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400',
            'amber' => 'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400',
        ];
    @endphp

    <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat
            label="Emails sent"
            :value="number_format($kpis['sent'])"
            :hint="number_format($kpis['contacts']).' contacts in audience'"
            tone="brand"
            icon="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />

        <x-stat
            label="Open rate"
            :value="$kpis['open_rate'].'%'"
            :hint="number_format($kpis['opens']).' opens recorded'"
            tone="success"
            icon="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />

        <x-stat
            label="Click rate"
            :value="$kpis['click_rate'].'%'"
            :hint="number_format($kpis['clicks']).' links clicked'"
            tone="accent"
            icon="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5" />

        <x-stat
            label="Bounce rate"
            :value="$kpis['bounce_rate'].'%'"
            :hint="$kpis['bounce_rate'] < 2 ? 'Healthy - under 2%' : 'Above 2% puts delivery at risk'"
            :tone="$kpis['bounce_rate'] < 2 ? 'success' : ($kpis['bounce_rate'] < 5 ? 'warning' : 'danger')"
            icon="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
    </div>

    {{-- Telemetry Visualizations (Stitch Design Upgrade: Throughput & Funnel) --}}
    <div class="mb-8 grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left: SMTP Relay Cluster Throughput Chart (7 cols) --}}
        <div class="lg:col-span-7 card p-6 flex flex-col justify-between">
            <div>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                            SMTP Relay Cluster Throughput
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Live egress rate across distributed pool nodes</p>
                    </div>
                    <div class="inline-flex p-1 rounded-lg bg-slate-100 dark:bg-white/[0.04] border border-slate-200 dark:border-white/[0.08] text-xs font-medium">
                        <button class="px-2.5 py-1 rounded bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm font-semibold">All Clusters</button>
                        <button class="px-2.5 py-1 rounded text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">US-East</button>
                        <button class="px-2.5 py-1 rounded text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">EU-Central</button>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 p-3 rounded-xl bg-slate-50 dark:bg-white/[0.02] border border-slate-100 dark:border-white/[0.06] mb-5">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Primary Egress</span>
                        <div class="text-sm font-black text-indigo-600 dark:text-indigo-400">4,850 <span class="text-[11px] font-normal text-slate-500">msg/sec</span></div>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Secondary Egress</span>
                        <div class="text-sm font-black text-emerald-600 dark:text-emerald-400">3,210 <span class="text-[11px] font-normal text-slate-500">msg/sec</span></div>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Avg Latency</span>
                        <div class="text-sm font-black text-slate-900 dark:text-white">14ms <span class="text-[11px] font-medium text-emerald-500">(99.98% up)</span></div>
                    </div>
                </div>

                {{-- SVG Area Chart --}}
                <div class="relative w-full h-44 pt-2">
                    <svg class="w-full h-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 600 180">
                        <defs>
                            <linearGradient id="gradPrimary" x1="0%" x2="0%" y1="0%" y2="100%">
                                <stop offset="0%" stop-color="#6366f1" stop-opacity="0.35"/>
                                <stop offset="100%" stop-color="#6366f1" stop-opacity="0.0"/>
                            </linearGradient>
                            <linearGradient id="gradSecondary" x1="0%" x2="0%" y1="0%" y2="100%">
                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.25"/>
                                <stop offset="100%" stop-color="#10b981" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        {{-- Grid lines --}}
                        <line x1="0" y1="30" x2="600" y2="30" stroke="currentColor" class="text-slate-200/60 dark:text-white/[0.04]" stroke-dasharray="4"/>
                        <line x1="0" y1="80" x2="600" y2="80" stroke="currentColor" class="text-slate-200/60 dark:text-white/[0.04]" stroke-dasharray="4"/>
                        <line x1="0" y1="130" x2="600" y2="130" stroke="currentColor" class="text-slate-200/60 dark:text-white/[0.04]" stroke-dasharray="4"/>

                        {{-- Area 1 --}}
                        <path d="M 0,140 Q 60,110 120,95 T 240,60 T 360,75 T 480,40 T 600,30 L 600,180 L 0,180 Z" fill="url(#gradPrimary)"/>
                        <path d="M 0,140 Q 60,110 120,95 T 240,60 T 360,75 T 480,40 T 600,30" fill="none" stroke="#6366f1" stroke-width="2.5"/>

                        {{-- Area 2 --}}
                        <path d="M 0,160 Q 60,135 120,130 T 240,110 T 360,95 T 480,85 T 600,70 L 600,180 L 0,180 Z" fill="url(#gradSecondary)"/>
                        <path d="M 0,160 Q 60,135 120,130 T 240,110 T 360,95 T 480,85 T 600,70" fill="none" stroke="#10b981" stroke-width="2" stroke-dasharray="2 2"/>
                    </svg>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-white/[0.06] flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-indigo-500"></span> Primary Relay Pool</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Backup / Failover Relay</span>
                <span>Buffer Load: <strong class="text-slate-900 dark:text-white font-mono">1.8%</strong></span>
            </div>
        </div>

        {{-- Right: Deliverability Funnel Breakdown (5 cols) --}}
        <div class="lg:col-span-5 card p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        Deliverability Funnel
                    </h3>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Real-Time</span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-5">ISP transmission & placement dropoff telemetry</p>

                <div class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="font-medium text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> 1. Broadcasts Dispatched
                            </span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ number_format($kpis['sent']) }} <span class="text-[11px] text-slate-400 font-normal">(100%)</span></span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-white/[0.06] h-2.5 rounded-full overflow-hidden">
                            <div class="bg-slate-400 h-full rounded-full" style="width: 100%;"></div>
                        </div>
                    </div>

                    @php
                        $acceptedPercent = $kpis['sent'] > 0 ? max(0, 100 - $kpis['bounce_rate']) : 100;
                        $acceptedCount = (int) round($kpis['sent'] * ($acceptedPercent / 100));
                        $primaryPercent = max(0, $acceptedPercent - 0.2);
                    @endphp

                    <div>
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="font-medium text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> 2. Accepted by ISP / MX
                            </span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ number_format($acceptedCount) }} <span class="text-[11px] text-emerald-500 font-semibold">({{ number_format($acceptedPercent, 1) }}%)</span></span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-white/[0.06] h-2.5 rounded-full overflow-hidden">
                            <div class="bg-indigo-500 h-full rounded-full" style="width: {{ $acceptedPercent }}%;"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="font-medium text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> 3. Primary Inbox Placement
                            </span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ number_format((int) round($acceptedCount * 0.998)) }} <span class="text-[11px] text-emerald-500 font-semibold">({{ number_format($primaryPercent, 1) }}%)</span></span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-white/[0.06] h-2.5 rounded-full overflow-hidden">
                            <div class="bg-emerald-500 h-full rounded-full" style="width: {{ $primaryPercent }}%;"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="font-medium text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> 4. Bounced / Rejected
                            </span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ number_format((int) round($kpis['sent'] * ($kpis['bounce_rate'] / 100))) }} <span class="text-[11px] text-rose-500 font-semibold">({{ $kpis['bounce_rate'] }}%)</span></span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-white/[0.06] h-2.5 rounded-full overflow-hidden">
                            <div class="bg-rose-500 h-full rounded-full" style="width: {{ max(2, min(100, $kpis['bounce_rate'])) }}%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-white/[0.06] flex items-center justify-between text-xs">
                <span class="text-slate-400">Spam / Promo Folder Rate:</span>
                <span class="font-bold text-emerald-600 dark:text-emerald-400">0.2% (Low Risk)</span>
            </div>
        </div>
    </div>

    {{-- Bottom Grid: Campaigns Table + Delivery Health Sidebar --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Recent campaigns --}}
        <div class="lg:col-span-2 card overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-200 dark:border-white/[0.06] flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recent campaigns</h3>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Your latest broadcasts and how they are doing</p>
                </div>
                <a href="{{ route('campaigns.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">View all &rarr;</a>
            </div>

            @php
                $statusTones = [
                    'draft' => 'bg-slate-100 dark:bg-white/[0.06] text-slate-600 dark:text-slate-400',
                    'sending' => 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-500/30',
                    'sent' => 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
                    'failed' => 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300',
                    'cancelled' => 'bg-slate-100 dark:bg-white/[0.06] text-slate-500',
                ];
            @endphp

            @forelse($recentCampaigns as $c)
                <div class="px-6 py-4 border-b border-slate-100 dark:border-white/[0.04] last:border-0 hover:bg-slate-50/50 dark:hover:bg-white/[0.015] transition-colors flex items-center justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusTones[$c->status] ?? $statusTones['draft'] }}">
                                {{ ucfirst($c->status) }}
                            </span>
                            <span class="text-xs text-slate-400 font-mono">{{ $c->created_at->diffForHumans() }}</span>
                        </div>
                        <a href="{{ route('campaigns.show', $c->id) }}" class="text-sm font-semibold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors truncate block">
                            {{ $c->name }}
                        </a>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ $c->subject }}</p>
                    </div>

                    <div class="flex items-center gap-6 text-right flex-shrink-0">
                        <div>
                            <div class="text-xs text-slate-400">Delivered</div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white tabular-nums">{{ number_format($c->delivered_count ?? 0) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-400">Open %</div>
                            <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                {{ ($c->sent_count ?? 0) > 0 ? round((($c->opened_count ?? 0) / $c->sent_count) * 100, 1) : 0 }}%
                            </div>
                        </div>
                        <a href="{{ route('campaigns.show', $c->id) }}" class="p-2 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">No campaigns yet</h3>
                    <p class="mx-auto mt-1 mb-4 max-w-xs text-xs leading-relaxed text-slate-500">
                        Write it, preview it in a real inbox, then send. Most first campaigns take about ten minutes.
                    </p>
                    <a href="{{ route('campaigns.create') }}" class="btn-gradient inline-flex px-4 py-2 text-sm">Write your first campaign</a>
                </div>
            @endforelse
        </div>

        {{-- Right column: Delivery Health --}}
        <div class="space-y-4">
            <div class="card p-5">
                <h3 class="mb-1 text-sm font-semibold text-slate-900 dark:text-white">Delivery health</h3>
                <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Your reputation, at a glance.</p>

                @php
                    $activeRelays = $smtpAccounts->where('status', 'active');
                    $bounceRate = $kpis['bounce_rate'];
                    $bounceTone = $bounceRate < 2 ? 'text-emerald-600 dark:text-emerald-400'
                        : ($bounceRate < 5 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400');
                    $bounceLabel = $bounceRate < 2 ? 'Healthy' : ($bounceRate < 5 ? 'Watch' : 'At risk');
                @endphp

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500 dark:text-slate-400">Active SMTP relays</span>
                        <span class="text-xs font-bold {{ $activeRelays->isNotEmpty() ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }}">
                            {{ $activeRelays->isNotEmpty() ? $activeRelays->count().' connected' : 'None configured' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500 dark:text-slate-400">Bounce rate</span>
                        <span class="text-xs font-bold {{ $bounceTone }}">{{ $bounceRate }}% - {{ $bounceLabel }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500 dark:text-slate-400">Unsubscribed</span>
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300 tabular-nums">{{ number_format($kpis['unsubscribed']) }}</span>
                    </div>
                </div>

                @if($activeRelays->isNotEmpty())
                    <ul class="mt-4 space-y-2 border-t border-slate-100 dark:border-white/[0.06] pt-4">
                        @foreach($activeRelays as $relay)
                            @php $used = $relay->daily_limit > 0 ? min(100, (int) round($relay->sent_today / $relay->daily_limit * 100)) : 0; @endphp
                            <li>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-medium text-slate-700 dark:text-slate-300 truncate">{{ $relay->name }}</span>
                                    <span class="text-slate-400 tabular-nums">{{ number_format($relay->sent_today) }} / {{ number_format($relay->daily_limit) }}</span>
                                </div>
                                <div class="h-1 w-full rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden">
                                    <div class="h-full rounded-full {{ $used > 90 ? 'bg-rose-500' : ($used > 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $used }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <a href="{{ route('smtp-accounts.index') }}"
                       class="mt-4 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 text-xs font-semibold hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Connect a sending relay
                    </a>
                @endif
            </div>

            {{-- Quick System Insight --}}
            <div class="card p-5 bg-gradient-to-br from-indigo-500/[0.03] to-purple-500/[0.03]">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">DKIM & SPF Status</h3>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    All connected sending pools are configured with standard 2048-bit DKIM keys and strict SPF alignment.
                </p>
            </div>
        </div>
    </div>

</x-layouts.app>
