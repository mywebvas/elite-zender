<x-layouts.app header="Campaigns">

<div class="page-header">
    <div>
        <h1 class="page-title">Campaigns</h1>
        <p class="page-subtitle">Manage your email broadcasts and view their performance.</p>
    </div>
    <a href="{{ route('campaigns.create') }}" class="btn-gradient">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Campaign
    </a>
</div>

{{-- Filter chips --}}
<div class="flex items-center gap-2 mb-6 overflow-x-auto pb-1"
     x-data="{ filter: 'all' }">
    @foreach(['all','draft','sending','completed','paused'] as $f)
    <button @click="filter = '{{ $f }}'"
            :class="filter === '{{ $f }}' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-white/10 ring-1 ring-slate-200 dark:ring-white/10'"
            :aria-pressed="filter === '{{ $f }}'"
            class="px-4 py-1.5 rounded-full text-sm font-semibold transition-all whitespace-nowrap">
        {{ ucfirst($f === 'all' ? 'All campaigns' : $f) }}
    </button>
    @endforeach
</div>

{{-- Campaign Cards --}}
@forelse($campaigns as $campaign)
@php
    // Real deliveries, not the size of the list. The previous fallback ran a
    // COUNT against the contact list for every row on the page — 25 extra
    // queries — and then presented the answer as "Sent".
    $sentCount = (int) $campaign->sent_count;
    $opens = $campaign->opens_count ?? 0;
    $clicks = $campaign->clicks_count ?? 0;
    $openPct = $sentCount > 0 ? round(($opens / $sentCount) * 100, 1) : 0;
    $clickPct = $sentCount > 0 ? round(($clicks / $sentCount) * 100, 1) : 0;
    $statusMeta = match($campaign->status) {
        'completed' => ['label'=>'Completed', 'cls'=>'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'],
        'sending'   => ['label'=>'Sending',   'cls'=>'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-400'],
        'paused'    => ['label'=>'Paused',    'cls'=>'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400'],
        default     => ['label'=>'Draft',     'cls'=>'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400'],
    };
@endphp
<div class="card p-6 mb-4 flex flex-col sm:flex-row sm:items-center gap-4 hover:ring-indigo-500/20 transition-all duration-150"
     x-show="filter === 'all' || filter === '{{ $campaign->status }}'">

    {{-- Icon --}}
    <div class="w-11 h-11 rounded-xl bg-indigo-50 dark:bg-indigo-500/15 flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
    </div>

    {{-- Name + meta --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
            <h3 class="text-base font-bold text-slate-900 dark:text-white truncate">{{ $campaign->name }}</h3>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusMeta['cls'] }}">{{ $statusMeta['label'] }}</span>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            {{ $campaign->updated_at->format('M d, Y') }}
            @if($campaign->list) · {{ $campaign->list->name }} @endif
        </p>
    </div>

    {{-- Stats --}}
    <div class="flex items-center gap-6 shrink-0">
        <div class="text-center">
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Sent</p>
            <p class="text-base font-bold text-slate-900 dark:text-white">{{ number_format($sentCount) }}</p>
        </div>
        <div class="text-center">
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Opens</p>
            <p class="text-base font-bold {{ $openPct > 20 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white' }}">{{ $openPct }}%</p>
        </div>
        <div class="text-center">
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Clicks</p>
            <p class="text-base font-bold text-slate-900 dark:text-white">{{ $clickPct }}%</p>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-2 shrink-0">
        @if($campaign->status === 'draft')
        <form action="{{ route('campaigns.dispatch', $campaign) }}" method="POST"
              onsubmit="return confirm('Send this campaign to all contacts in the selected list?')">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-bold hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Send
            </button>
        </form>
        @endif

        <a href="{{ route('campaigns.show', $campaign->id) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-white/5 text-slate-600 dark:text-slate-400 text-xs font-semibold hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
            View
        </a>

        <form action="{{ route('campaigns.destroy', $campaign) }}" method="POST"
              onsubmit="return confirm('Permanently delete this campaign?')">
            @csrf @method('DELETE')
            <button type="submit"
                    class="inline-flex items-center px-2 py-1.5 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </form>
    </div>
</div>
@empty
<div class="card p-16 text-center">
    <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center mx-auto mb-5">
        <svg class="w-8 h-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
    </div>
    <h3 class="text-lg font-bold text-slate-900 dark:text-white">No campaigns yet</h3>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 mb-6 max-w-sm mx-auto">
        Create your first campaign to start sending beautiful emails to your audience.
    </p>
    <a href="{{ route('campaigns.create') }}" class="btn-gradient py-3 px-6">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Create First Campaign
    </a>
</div>
@endforelse

</x-layouts.app>
