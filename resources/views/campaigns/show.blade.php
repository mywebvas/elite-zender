<x-layouts.app header="Campaign Overview">

    <div class="page-header">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="page-title">{{ $campaign->name }}</h1>
                @php
                    $statusCls = match($campaign->status) {
                        'completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
                        'sending'   => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-400',
                        'paused'    => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
                        default     => 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400',
                    };
                @endphp
                <span class="badge-base {{ $statusCls }}">{{ ucfirst($campaign->status) }}</span>
            </div>
            <p class="page-subtitle">Subject: {{ collect(explode('||', $campaign->subject))->first() }}</p>
        </div>
        
        <div class="flex items-center gap-2">
            @if($campaign->status === 'draft')
                <a href="{{ route('campaigns.edit', $campaign->id) }}" class="btn-gradient">Edit Content</a>
                <form action="{{ route('campaigns.dispatch', $campaign->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20 transition-all">Send Now</button>
                </form>
            @endif
            
            @if(in_array($campaign->status, ['completed', 'sent']))
                <form action="{{ route('campaigns.retarget', $campaign->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-sky-700 bg-sky-50 hover:bg-sky-100 dark:bg-sky-500/10 dark:text-sky-400 dark:hover:bg-sky-500/20 transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                        </svg>
                        Retarget Non-Openers
                    </button>
                </form>
            @endif
            
            <form action="{{ route('campaigns.destroy', $campaign->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this campaign?');">
                @csrf @method('DELETE')
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-all" {{ $campaign->status === 'sending' ? 'disabled' : '' }}>
                    Delete
                </button>
            </form>
        </div>
    </div>

    @php
        // `sent_count` is the number of messages actually handed to a relay.
        // This used to read a `stats_cache` key that nothing has ever written,
        // and fell back to counting the list — so an untouched draft reported
        // a five-figure "Total Sent", and every open rate was divided by the
        // size of the audience instead of the number of deliveries.
        $sentCount = (int) $campaign->sent_count;
        $opens = $campaign->opens_count ?? 0;
        $clicks = $campaign->clicks_count ?? 0;
        $openPct = $sentCount > 0 ? round(($opens / $sentCount) * 100, 1) : 0;
        $clickPct = $sentCount > 0 ? round(($clicks / $sentCount) * 100, 1) : 0;
    @endphp

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card p-5">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">Total Sent</p>
            <p class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ number_format($sentCount) }}</p>
            @if($campaign->recipients_count > 0)
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    of {{ number_format($campaign->recipients_count) }} recipients
                    @if($campaign->failed_count > 0) · {{ number_format($campaign->failed_count) }} failed @endif
                    @if($campaign->skipped_count > 0) · {{ number_format($campaign->skipped_count) }} skipped @endif
                </p>
            @endif
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">Opens</p>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ number_format($opens) }}</p>
                @if($sentCount > 0)
                <p class="text-sm font-semibold {{ $openPct > 20 ? 'text-emerald-500' : 'text-slate-400' }} mb-1">{{ $openPct }}%</p>
                @endif
            </div>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">Clicks</p>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ number_format($clicks) }}</p>
                @if($sentCount > 0)
                <p class="text-sm font-semibold text-slate-400 mb-1">{{ $clickPct }}%</p>
                @endif
            </div>
        </div>
        <div class="card p-5 flex flex-col justify-center bg-indigo-50/50 dark:bg-indigo-500/5 ring-1 ring-indigo-100 dark:ring-indigo-500/10">
            <p class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider mb-1">Target Audience</p>
            <p class="text-base font-bold text-slate-900 dark:text-white">{{ $campaign->list ? $campaign->list->name : 'No List Assigned' }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                @if($campaign->smtpAccounts->count() > 0)
                    {{ $campaign->smtpAccounts->count() }} SMTP(s) rotated
                @else
                    Default Tenant SMTPs
                @endif
            </p>
        </div>
    </div>

    {{-- Content Preview --}}
    <div class="card overflow-hidden flex flex-col lg:flex-row h-[600px]">
        <div class="w-full lg:w-1/3 bg-slate-50 dark:bg-[#0a0a0f] border-b lg:border-b-0 lg:border-r border-slate-200 dark:border-white/10 p-6 overflow-y-auto">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-6">Delivery Details</h3>
            
            <div class="space-y-6">
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">From</p>
                    {{-- Campaigns carry no sender identity of their own: it comes
                         from whichever relay in the pool sends the message. --}}
                    @forelse($campaign->smtpAccounts as $relay)
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $relay->from_name }} &lt;{{ $relay->from_email }}&gt;
                        </p>
                    @empty
                        <p class="text-sm font-semibold text-amber-600 dark:text-amber-400">
                            No SMTP relay assigned
                        </p>
                    @endforelse
                </div>
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Subject</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                        {{ collect(explode('||', $campaign->subject))->first() }}
                    </p>
                    @if(str_contains($campaign->subject, '||'))
                    <span class="mt-2 inline-block px-2 py-1 rounded bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-[10px] font-bold uppercase tracking-wider">A/B Spintax Active</span>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Created</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $campaign->created_at->format('M d, Y h:i A') }}</p>
                </div>
            </div>
        </div>
        <div class="flex-1 bg-white dark:bg-white flex flex-col">
            <div class="px-4 py-3 bg-slate-100 border-b border-slate-200 flex items-center justify-between">
                <div class="flex gap-1.5">
                    <div class="w-3 h-3 rounded-full bg-rose-400"></div>
                    <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                    <div class="w-3 h-3 rounded-full bg-emerald-400"></div>
                </div>
                <span class="text-xs font-semibold text-slate-500">Live HTML Preview</span>
            </div>
            <iframe 
                class="w-full h-full border-0 bg-white" 
                sandbox="allow-same-origin"
                srcdoc="{{ htmlspecialchars($campaign->body_html ?? nl2br(e($campaign->body_text))) }}">
            </iframe>
        </div>
    </div>

</x-layouts.app>
