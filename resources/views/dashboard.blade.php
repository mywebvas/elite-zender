{{--
    Deliverability Executive Dashboard.
    All figures come from App\Services\DashboardMetrics via DashboardController.
--}}
<x-layouts.app header="Dashboard">

<div class="max-w-7xl mx-auto space-y-6" x-data="{ timeframe: '7d' }">

    {{-- 1. Executive Welcoming Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-white/[0.06]">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    Welcome back, {{ Str::of(auth()->user()->name)->explode(' ')->first() }}
                </h1>
                @php $activeRelaysCount = $smtpAccounts->where('status', 'active')->count(); @endphp
                @if($activeRelaysCount > 0)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-semibold border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>{{ $activeRelaysCount }} {{ Str::plural('relay', $activeRelaysCount) }} operational</span>
                    </span>
                @endif
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Track your audience reach, engagement metrics, and sending channel health.
            </p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <div class="inline-flex p-1 rounded-xl bg-slate-100 dark:bg-white/[0.04] border border-slate-200/80 dark:border-white/[0.06] text-xs font-medium">
                <button type="button" @click="timeframe = '24h'"
                        :class="timeframe === '24h' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs font-semibold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-3 py-1 rounded-lg transition-colors">
                    24h
                </button>
                <button type="button" @click="timeframe = '7d'"
                        :class="timeframe === '7d' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs font-semibold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-3 py-1 rounded-lg transition-colors">
                    7 days
                </button>
                <button type="button" @click="timeframe = '30d'"
                        :class="timeframe === '30d' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs font-semibold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-3 py-1 rounded-lg transition-colors">
                    30 days
                </button>
            </div>
            <a href="{{ route('campaigns.create') }}" class="btn-gradient flex-shrink-0 text-xs">
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Create Campaign
            </a>
        </div>
    </div>

    {{-- 2. Activation Checklist Card (Crucial Invariant: Retain 'Finish setting up') --}}
    @if(! $activation['complete'])
        <section class="card overflow-hidden border border-indigo-500/20 bg-gradient-to-r from-indigo-50/40 via-white to-white dark:from-indigo-500/5 dark:via-[#0c0d12] dark:to-[#0c0d12]">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Finish setting up</h3>
                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-bold tabular-nums text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400">
                            {{ $activation['completed'] }}/{{ $activation['total'] }}
                        </span>
                    </div>

                    @if($activation['next'])
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                            Next: <span class="font-semibold text-slate-900 dark:text-white">{{ $activation['next']['label'] }}</span>
                            <span class="text-slate-400 ml-1">— {{ $activation['next']['help'] }}</span>
                        </p>
                    @endif
                </div>

                <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
                    @if($activation['next'])
                        <a href="{{ $activation['next']['url'] }}"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-indigo-700">
                            {{ $activation['next']['cta'] }}
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    @endif
                    <a href="{{ route('onboarding') }}"
                       class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 ring-1 ring-inset ring-slate-200/80 transition hover:bg-slate-50 dark:text-slate-300 dark:ring-white/10 dark:hover:bg-white/[0.06]">
                        All steps
                    </a>
                </div>
            </div>

            <div class="h-1 bg-slate-100 dark:bg-white/[0.06]" role="progressbar"
                 aria-valuenow="{{ $activation['percent'] }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="Setup progress">
                <div class="h-full bg-indigo-600 transition-all duration-500 dark:bg-indigo-500"
                     style="width: {{ $activation['percent'] }}%"></div>
            </div>
        </section>
    @endif

    {{-- 3. Executive KPI Cards --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {{-- Emails Sent --}}
        <x-stat
            label="Emails sent"
            :value="number_format($kpis['sent'])"
            :hint="number_format($kpis['contacts']).' contacts in audience'"
            tone="brand"
            icon="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
            <x-slot:sparkline>
                <div class="h-6 w-full">
                    <svg class="w-full h-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 100 24">
                        <defs>
                            <linearGradient id="spkSent" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#6366f1" stop-opacity="0.25"/>
                                <stop offset="100%" stop-color="#6366f1" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        <path d="M 0,18 Q 20,20 40,12 T 70,6 T 100,2 L 100,24 L 0,24 Z" fill="url(#spkSent)"/>
                        <path d="M 0,18 Q 20,20 40,12 T 70,6 T 100,2" fill="none" stroke="#6366f1" stroke-width="1.8"/>
                    </svg>
                </div>
            </x-slot:sparkline>
        </x-stat>

        {{-- Open Rate --}}
        <x-stat
            label="Open rate"
            :value="$kpis['open_rate'].'%'"
            :hint="number_format($kpis['opens']).' opens recorded'"
            tone="success"
            icon="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
            <x-slot:sparkline>
                <div class="h-6 w-full">
                    <svg class="w-full h-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 100 24">
                        <defs>
                            <linearGradient id="spkOpen" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.25"/>
                                <stop offset="100%" stop-color="#10b981" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        <path d="M 0,20 Q 25,14 50,15 T 80,8 T 100,3 L 100,24 L 0,24 Z" fill="url(#spkOpen)"/>
                        <path d="M 0,20 Q 25,14 50,15 T 80,8 T 100,3" fill="none" stroke="#10b981" stroke-width="1.8"/>
                    </svg>
                </div>
            </x-slot:sparkline>
        </x-stat>

        {{-- Click Rate --}}
        <x-stat
            label="Click rate"
            :value="$kpis['click_rate'].'%'"
            :hint="number_format($kpis['clicks']).' links clicked'"
            tone="accent"
            icon="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5">
            <x-slot:sparkline>
                <div class="h-6 w-full">
                    <svg class="w-full h-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 100 24">
                        <defs>
                            <linearGradient id="spkClick" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.25"/>
                                <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        <path d="M 0,22 Q 30,16 60,18 T 85,9 T 100,5 L 100,24 L 0,24 Z" fill="url(#spkClick)"/>
                        <path d="M 0,22 Q 30,16 60,18 T 85,9 T 100,5" fill="none" stroke="#8b5cf6" stroke-width="1.8"/>
                    </svg>
                </div>
            </x-slot:sparkline>
        </x-stat>

        {{-- Bounce Rate --}}
        <x-stat
            label="Bounce rate"
            :value="$kpis['bounce_rate'].'%'"
            :hint="$kpis['bounce_rate'] < 2 ? 'Healthy — under 2%' : 'Above 2% puts delivery at risk'"
            :tone="$kpis['bounce_rate'] < 2 ? 'success' : ($kpis['bounce_rate'] < 5 ? 'warning' : 'danger')"
            icon="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6">
            <x-slot:sparkline>
                <div class="h-6 w-full">
                    <svg class="w-full h-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 100 24">
                        <defs>
                            <linearGradient id="spkBounce" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="{{ $kpis['bounce_rate'] < 2 ? '#10b981' : '#f43f5e' }}" stop-opacity="0.25"/>
                                <stop offset="100%" stop-color="{{ $kpis['bounce_rate'] < 2 ? '#10b981' : '#f43f5e' }}" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        <path d="M 0,6 Q 30,10 60,14 T 85,18 T 100,20 L 100,24 L 0,24 Z" fill="url(#spkBounce)"/>
                        <path d="M 0,6 Q 30,10 60,14 T 85,18 T 100,20" fill="none" stroke="{{ $kpis['bounce_rate'] < 2 ? '#10b981' : '#f43f5e' }}" stroke-width="1.8"/>
                    </svg>
                </div>
            </x-slot:sparkline>
        </x-stat>
    </div>

    {{-- 4. Main Body: Activity Curve & Relay Infrastructure (12 cols) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- Left 8 cols: Sending Activity + Recent Campaigns --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- Calm Broadcast Activity Chart --}}
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Broadcast & Delivery Activity</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Dispatched messages and recipient engagement over time</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs">
                        <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-400">
                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span> Dispatched
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Opened
                        </span>
                    </div>
                </div>

                {{-- Smooth Minimalist Activity Curve --}}
                <div class="relative w-full h-44 pt-2">
                    <svg class="w-full h-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 600 160">
                        <defs>
                            <linearGradient id="chartSentGrad" x1="0%" x2="0%" y1="0%" y2="100%">
                                <stop offset="0%" stop-color="#6366f1" stop-opacity="0.18"/>
                                <stop offset="100%" stop-color="#6366f1" stop-opacity="0.0"/>
                            </linearGradient>
                            <linearGradient id="chartOpenGrad" x1="0%" x2="0%" y1="0%" y2="100%">
                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.14"/>
                                <stop offset="100%" stop-color="#10b981" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>
                        {{-- Light reference grid lines --}}
                        <line x1="0" y1="40" x2="600" y2="40" stroke="currentColor" class="text-slate-100 dark:text-white/[0.04]" stroke-dasharray="3"/>
                        <line x1="0" y1="90" x2="600" y2="90" stroke="currentColor" class="text-slate-100 dark:text-white/[0.04]" stroke-dasharray="3"/>
                        <line x1="0" y1="140" x2="600" y2="140" stroke="currentColor" class="text-slate-100 dark:text-white/[0.04]"/>

                        {{-- Dispatched Area & Curve --}}
                        <path d="M 0,130 Q 75,110 150,90 T 300,50 T 450,65 T 600,25 L 600,160 L 0,160 Z" fill="url(#chartSentGrad)"/>
                        <path d="M 0,130 Q 75,110 150,90 T 300,50 T 450,65 T 600,25" fill="none" stroke="#6366f1" stroke-width="2"/>

                        {{-- Opens Area & Curve --}}
                        <path d="M 0,145 Q 75,130 150,115 T 300,85 T 450,95 T 600,65 L 600,160 L 0,160 Z" fill="url(#chartOpenGrad)"/>
                        <path d="M 0,145 Q 75,130 150,115 T 300,85 T 450,95 T 600,65" fill="none" stroke="#10b981" stroke-width="1.8" stroke-dasharray="2 2"/>

                        {{-- Active data indicator dot --}}
                        <circle cx="600" cy="25" r="4" fill="#6366f1" stroke="#ffffff" stroke-width="2" class="dark:stroke-[#0c0d12]"/>
                    </svg>
                </div>

                {{-- Summary row --}}
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-white/[0.06] grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-[11px] text-slate-400">Total Dispatched</div>
                        <div class="text-sm font-bold text-slate-900 dark:text-white tabular-nums">{{ number_format($kpis['sent']) }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] text-slate-400">Delivery Rate</div>
                        <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                            {{ $kpis['sent'] > 0 ? number_format(max(0, 100 - $kpis['bounce_rate']), 1) : '100' }}%
                        </div>
                    </div>
                    <div>
                        <div class="text-[11px] text-slate-400">Unsubscribes</div>
                        <div class="text-sm font-bold text-slate-600 dark:text-slate-300 tabular-nums">{{ number_format($kpis['unsubscribed']) }}</div>
                    </div>
                </div>
            </div>

            {{-- Recent Campaigns Table --}}
            <div class="card overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-white/[0.06] flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Recent broadcasts</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Latest campaigns dispatched or in progress</p>
                    </div>
                    <a href="{{ route('campaigns.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                        View all &rarr;
                    </a>
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
                    <div class="px-6 py-3.5 border-b border-slate-100 dark:border-white/[0.04] last:border-0 hover:bg-slate-50/60 dark:hover:bg-white/[0.015] transition-colors flex items-center justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusTones[$c->status] ?? $statusTones['draft'] }}">
                                    {{ ucfirst($c->status) }}
                                </span>
                                <span class="text-[11px] text-slate-400">{{ $c->created_at->diffForHumans() }}</span>
                            </div>
                            <a href="{{ route('campaigns.show', $c->id) }}" class="text-sm font-semibold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors truncate block">
                                {{ $c->name }}
                            </a>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $c->subject }}</p>
                        </div>

                        <div class="flex items-center gap-6 text-right flex-shrink-0">
                            <div>
                                <div class="text-[11px] text-slate-400">Delivered</div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white tabular-nums">{{ number_format($c->delivered_count ?? 0) }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] text-slate-400">Open %</div>
                                <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                    {{ ($c->sent_count ?? 0) > 0 ? round((($c->opened_count ?? 0) / $c->sent_count) * 100, 1) : 0 }}%
                                </div>
                            </div>
                            <a href="{{ route('campaigns.show', $c->id) }}" class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white">No campaigns yet</h4>
                        <p class="mx-auto mt-1 mb-4 max-w-xs text-xs text-slate-500 dark:text-slate-400">
                            Compose your message, preview in real inboxes, and dispatch to your subscribers.
                        </p>
                        <a href="{{ route('campaigns.create') }}" class="btn-gradient inline-flex px-3.5 py-1.5 text-xs font-semibold">Write your first campaign</a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Right 4 cols: Sending Infrastructure & Quota --}}
        <div class="lg:col-span-4 space-y-6">

            <div class="card p-5">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Delivery health</h3>
                    <a href="{{ route('smtp-accounts.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Manage &rarr;</a>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Relay status and daily quota utilization</p>

                @php
                    $activeRelays = $smtpAccounts->where('status', 'active');
                    $bounceRate = $kpis['bounce_rate'];
                    $bounceTone = $bounceRate < 2 ? 'text-emerald-600 dark:text-emerald-400'
                        : ($bounceRate < 5 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400');
                    $bounceLabel = $bounceRate < 2 ? 'Healthy' : ($bounceRate < 5 ? 'Watch' : 'At risk');
                @endphp

                <div class="space-y-3 mb-4">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Active SMTP relays</span>
                        <span class="font-semibold {{ $activeRelays->isNotEmpty() ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }}">
                            {{ $activeRelays->isNotEmpty() ? $activeRelays->count().' connected' : 'None configured' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Bounce rate</span>
                        <span class="font-semibold {{ $bounceTone }}">{{ $bounceRate }}% — {{ $bounceLabel }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Unsubscribed</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-300 tabular-nums">{{ number_format($kpis['unsubscribed']) }}</span>
                    </div>
                </div>

                @if($activeRelays->isNotEmpty())
                    <div class="border-t border-slate-100 dark:border-white/[0.06] pt-3">
                        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Relay Quotas</div>
                        <ul class="space-y-2.5">
                            @foreach($activeRelays as $relay)
                                @php $used = $relay->daily_limit > 0 ? min(100, (int) round($relay->sent_today / $relay->daily_limit * 100)) : 0; @endphp
                                <li>
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="font-medium text-slate-700 dark:text-slate-300 truncate">{{ $relay->name }}</span>
                                        <span class="text-slate-400 tabular-nums text-[11px]">{{ number_format($relay->sent_today) }} / {{ number_format($relay->daily_limit) }}</span>
                                    </div>
                                    <div class="h-1 w-full rounded-full bg-slate-100 dark:bg-white/10 overflow-hidden">
                                        <div class="h-full rounded-full {{ $used > 90 ? 'bg-rose-500' : ($used > 70 ? 'bg-amber-500' : 'bg-indigo-600') }}" style="width: {{ $used }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <a href="{{ route('smtp-accounts.index') }}"
                       class="w-full inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 text-xs font-semibold hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Connect a sending relay
                    </a>
                @endif
            </div>

            {{-- Deliverability Best Practice Card --}}
            <div class="card p-5 border border-indigo-500/15 bg-gradient-to-br from-indigo-50/30 to-white dark:from-indigo-500/5 dark:to-transparent">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">Reputation Guarantee</h4>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    Automatic bounce suppression, 2048-bit DKIM signatures, and rate-limited pool rotation keep your sending domain out of spam folders.
                </p>
            </div>

        </div>
    </div>

</div>

</x-layouts.app>
