{{--
    Dashboard.

    All figures come from App\Services\DashboardMetrics via DashboardController.
    This template used to run its own Eloquent queries inline (including a raw
    `DB::table('jobs')->count()`) and render a hard-coded "—" for every KPI, so
    the headline numbers were decorative.
--}}
<x-layouts.app header="Dashboard">

    {{-- Welcome --}}
    <div class="mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }},
                {{ Str::of(auth()->user()->name)->explode(' ')->first() }} 👋
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Here is what is happening with your campaigns today.</p>
        </div>
        <a href="{{ route('campaigns.create') }}" class="btn-gradient flex-shrink-0 w-full sm:w-auto">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            New Campaign
        </a>
    </div>

    {{-- KPI stats --}}
    @php
        $cards = [
            ['label' => 'Emails Sent', 'value' => number_format($kpis['sent']), 'hint' => number_format($kpis['contacts']).' contacts', 'color' => 'indigo', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ['label' => 'Open Rate', 'value' => $kpis['open_rate'].'%', 'hint' => number_format($kpis['opens']).' opens', 'color' => 'emerald', 'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'],
            ['label' => 'Click Rate', 'value' => $kpis['click_rate'].'%', 'hint' => number_format($kpis['clicks']).' clicks', 'color' => 'violet', 'icon' => 'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5'],
            ['label' => 'Bounce Rate', 'value' => $kpis['bounce_rate'].'%', 'hint' => number_format($kpis['bounces']).' bounces', 'color' => 'rose', 'icon' => 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'],
        ];
        $tints = [
            'indigo' => 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400',
            'emerald' => 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
            'violet' => 'bg-violet-50 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400',
            'rose' => 'bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400',
        ];
    @endphp

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach($cards as $card)
            <div class="card p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $card['label'] }}</p>
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center {{ $tints[$card['color']] }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-3xl font-black tracking-tight text-slate-900 dark:text-white">{{ $card['value'] }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $card['hint'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Recent campaigns --}}
        <div class="lg:col-span-2 card overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-200 dark:border-white/[0.06] flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Recent Campaigns</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Your latest email broadcasts</p>
                </div>
                <a href="{{ route('campaigns.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">View all →</a>
            </div>

            @php
                $statusStyles = [
                    'draft' => 'text-slate-500 bg-slate-100 dark:bg-white/5 dark:text-slate-400',
                    'queued' => 'text-sky-700 bg-sky-100 dark:bg-sky-500/15 dark:text-sky-400',
                    'sending' => 'text-indigo-700 bg-indigo-100 dark:bg-indigo-500/15 dark:text-indigo-400',
                    'paused' => 'text-amber-700 bg-amber-100 dark:bg-amber-500/15 dark:text-amber-400',
                    'completed' => 'text-emerald-700 bg-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-400',
                    'failed' => 'text-rose-700 bg-rose-100 dark:bg-rose-500/15 dark:text-rose-400',
                ];
            @endphp

            @forelse($recentCampaigns as $campaign)
                @if($loop->first)<div class="divide-y divide-slate-100 dark:divide-white/[0.04]">@endif
                <a href="{{ route('campaigns.show', $campaign) }}"
                   class="px-6 py-4 flex items-center gap-4 hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                    <div class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-500/15 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $campaign->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $campaign->list?->name ?? 'No list' }} · {{ $campaign->updated_at?->diffForHumans() }}
                        </p>
                    </div>

                    {{-- Live progress for anything mid-flight --}}
                    @if($campaign->status === 'sending' && $campaign->recipients_count > 0)
                        @php $pct = min(100, (int) round($campaign->sent_count / $campaign->recipients_count * 100)); @endphp
                        <div class="hidden sm:flex flex-col items-end gap-1 w-28">
                            <div class="h-1.5 w-full rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden">
                                <div class="h-full rounded-full bg-indigo-500 transition-[width] duration-500" style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="text-[10px] text-slate-400 tabular-nums">
                                {{ number_format($campaign->sent_count) }} / {{ number_format($campaign->recipients_count) }}
                            </span>
                        </div>
                    @endif

                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusStyles[$campaign->status] ?? $statusStyles['draft'] }}">
                        {{ ucfirst($campaign->status) }}
                    </span>
                </a>
                @if($loop->last)</div>@endif
            @empty
                <div class="px-6 py-12 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">No campaigns yet</h3>
                    <p class="text-xs text-slate-500 mt-1 mb-4">Create your first campaign to start sending.</p>
                    <a href="{{ route('campaigns.create') }}" class="btn-gradient text-sm py-2 px-4 inline-flex">Launch Campaign</a>
                </div>
            @endforelse
        </div>

        {{-- Right column --}}
        <div class="space-y-4">

            {{-- Quick actions --}}
            <div class="card p-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    @foreach([
                        ['label' => 'Launch Campaign', 'sub' => 'Create a new broadcast', 'href' => 'campaigns.create', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => 'indigo'],
                        ['label' => 'Import Contacts', 'sub' => 'Upload a CSV file', 'href' => 'contacts.index', 'icon' => 'M12 4v16m8-8H4', 'color' => 'emerald'],
                        ['label' => 'Add SMTP Account', 'sub' => 'Connect email provider', 'href' => 'smtp-accounts.index', 'icon' => 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2', 'color' => 'violet'],
                        ['label' => 'Build Automation', 'sub' => 'Set up drip sequences', 'href' => 'automations.create', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'color' => 'amber'],
                    ] as $action)
                        <a href="{{ route($action['href']) }}"
                           class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-white/[0.04] transition-colors group">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 {{ $tints[$action['color']] ?? 'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400' }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $action['icon'] }}"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{{ $action['label'] }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $action['sub'] }}</p>
                            </div>
                            <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 ml-auto group-hover:text-indigo-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Delivery health — real relay data, not a hard-coded "Excellent" --}}
            <div class="card p-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Delivery Health</h3>

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
                        <span class="text-xs font-bold {{ $bounceTone }}">{{ $bounceRate }}% — {{ $bounceLabel }}</span>
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
                        Add SMTP Account
                    </a>
                @endif
            </div>

        </div>
    </div>

</x-layouts.app>
