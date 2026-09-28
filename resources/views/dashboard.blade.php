<x-layouts.app header="Dashboard">

{{-- Welcome --}}
<div class="mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white tracking-tight">
            Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }} 👋
        </h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Here is what is happening with your campaigns today.</p>
    </div>
    <a href="{{ route('campaigns.create') }}" class="btn-gradient flex-shrink-0 w-full sm:w-auto">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Campaign
    </a>
</div>

{{-- KPI Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    @php
    $kpis = [
        ['label'=>'Emails Sent',     'value'=>'—', 'trend'=>null, 'color'=>'indigo',   'icon'=>'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ['label'=>'Open Rate',       'value'=>'—', 'trend'=>null, 'color'=>'emerald',  'icon'=>'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'],
        ['label'=>'Click Rate',      'value'=>'—', 'trend'=>null, 'color'=>'violet',   'icon'=>'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5'],
        ['label'=>'Bounce Rate',     'value'=>'—', 'trend'=>null, 'color'=>'rose',     'icon'=>'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'],
    ];
    @endphp

    @foreach($kpis as $kpi)
    <div class="card p-5 flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $kpi['label'] }}</p>
            <div class="w-8 h-8 rounded-lg flex items-center justify-center
                @if($kpi['color']==='indigo') bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400
                @elseif($kpi['color']==='emerald') bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400
                @elseif($kpi['color']==='violet') bg-violet-50 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400
                @else bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 @endif">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $kpi['icon'] }}"/>
                </svg>
            </div>
        </div>
        <div>
            <p class="text-3xl font-black tracking-tight text-slate-900 dark:text-white tracking-tight">{{ $kpi['value'] }}</p>
            <p class="text-xs text-slate-400 mt-0.5">All time</p>
        </div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Recent Campaigns Table --}}
    <div class="lg:col-span-2 card overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200 dark:border-white/[0.06] flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Recent Campaigns</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Your latest email broadcasts</p>
            </div>
            <a href="{{ route('campaigns.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">View all →</a>
        </div>
        @php $recentCampaigns = \App\Models\Campaign::where('tenant_id', auth()->user()->tenant_id)->latest()->limit(5)->get(); @endphp
        @if($recentCampaigns->isNotEmpty())
        <div class="divide-y divide-slate-100 dark:divide-white/[0.04]">
            @foreach($recentCampaigns as $c)
            <div class="px-6 py-4 flex items-center gap-4 hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                <div class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $c->name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $c->updated_at->diffForHumans() }}</p>
                </div>
                @php $statusColors = ['draft'=>'text-slate-500 bg-slate-100 dark:bg-white/5 dark:text-slate-400','sending'=>'text-indigo-700 bg-indigo-100 dark:bg-indigo-500/15 dark:text-indigo-400','completed'=>'text-emerald-700 bg-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-400','paused'=>'text-amber-700 bg-amber-100 dark:bg-amber-500/15 dark:text-amber-400']; @endphp
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusColors[$c->status] ?? $statusColors['draft'] }}">
                    {{ ucfirst($c->status) }}
                </span>
            </div>
            @endforeach
        </div>
        @else
        <div class="px-6 py-12 text-center">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">No campaigns yet</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4">Create your first campaign to start sending.</p>
            <a href="{{ route('campaigns.create') }}" class="btn-gradient text-sm py-2 px-4 inline-flex">Launch Campaign</a>
        </div>
        @endif
    </div>

    {{-- Right column --}}
    <div class="space-y-4">

        {{-- Quick Actions --}}
        <div class="card p-5">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Quick Actions</h3>
            <div class="space-y-2">
                @foreach([
                    ['label'=>'Launch Campaign',   'sub'=>'Create a new broadcast',    'href'=>'campaigns.create',     'icon'=>'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',    'color'=>'indigo'],
                    ['label'=>'Import Contacts',   'sub'=>'Upload a CSV file',         'href'=>'contacts.index',       'icon'=>'M12 4v16m8-8H4',                                                                                      'color'=>'emerald'],
                    ['label'=>'Add SMTP Account',  'sub'=>'Connect email provider',    'href'=>'smtp-accounts.index',  'icon'=>'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2', 'color'=>'violet'],
                    ['label'=>'Build Automation',  'sub'=>'Set up drip sequences',     'href'=>'automations.create',   'icon'=>'M13 10V3L4 14h7v7l9-11h-7z',                                                                         'color'=>'amber'],
                ] as $action)
                <a href="{{ route($action['href']) }}"
                   class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-white/[0.04] transition-colors group">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                        @if($action['color']==='indigo') bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400
                        @elseif($action['color']==='emerald') bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400
                        @elseif($action['color']==='violet') bg-violet-50 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400
                        @else bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 @endif">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $action['icon'] }}"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{{ $action['label'] }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $action['sub'] }}</p>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 ml-auto group-hover:text-indigo-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
                @endforeach
            </div>
        </div>

        {{-- Delivery Health --}}
        <div class="card p-5">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Delivery Health</h3>
            @php $smtpCount = \App\Models\SmtpAccount::where('tenant_id', auth()->user()->tenant_id)->where('status','active')->count(); @endphp
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Active SMTP accounts</span>
                    <span class="text-xs font-bold {{ $smtpCount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }}">
                        {{ $smtpCount > 0 ? $smtpCount . ' connected' : 'None configured' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Bounce rate</span>
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">0.0% — Excellent</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Queue status</span>
                    <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                        
                    @php $queueCount = \Illuminate\Support\Facades\DB::table('jobs')->count() ?? 0; @endphp
                    <span class="text-xs font-bold {{ $queueCount > 0 ? 'text-amber-500' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $queueCount > 0 ? $queueCount . ' jobs' : 'Idle' }}</span>
                    </div>
                </div>
            </div>

            @if($smtpCount === 0)
            <a href="{{ route('smtp-accounts.index') }}"
               class="mt-4 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 text-xs font-semibold hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Add SMTP Account
            </a>
            @endif
        </div>

    </div>
</div>

</x-layouts.app>



