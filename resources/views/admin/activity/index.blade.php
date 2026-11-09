<x-layouts.admin title="Audit trail" subtitle="Every operator action, who took it, and why.">
    {{-- Header Action & Stats Bar --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold tracking-tight text-white">Immutable Audit Trail</h2>
                    <span class="inline-flex items-center rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-0.5 text-xs font-mono font-medium text-slate-300">
                        {{ number_format($entries->total()) }} {{ Str::plural('event', $entries->total()) }}
                    </span>
                </div>
                <p class="text-xs text-slate-400">Attributable event log recording every privileged operator action and mutation.</p>
            </div>
        </div>

        {{-- CSV Export Link --}}
        <div>
            <a href="{{ route('admin.activity.export', request()->query()) }}"
               class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2.5 text-xs font-semibold text-slate-200 transition hover:bg-white/[0.08] hover:text-white"
               title="Export filtered records as CSV for external compliance audit">
                <svg class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="mb-6 rounded-2xl border border-white/[0.08] bg-[#0a0a12]/80 p-4 backdrop-blur-xl shadow-lg">
        <form method="GET" action="{{ route('admin.activity.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
            {{-- Operator Dropdown --}}
            <div class="relative">
                <select name="admin" @change="$el.form.requestSubmit()" class="w-full appearance-none rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 pr-8 text-xs text-white ring-1 ring-white/10 focus:bg-[#0e0e18] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                    <option value="" class="bg-[#0e0e18] text-white">Any operator</option>
                    @foreach($admins as $admin)
                        <option value="{{ $admin->id }}" class="bg-[#0e0e18] text-white" @selected(request('admin') === $admin->id)>
                            {{ $admin->name }}
                        </option>
                    @endforeach
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </div>
            </div>

            {{-- Action Dropdown --}}
            <div class="relative">
                <select name="action" @change="$el.form.requestSubmit()" class="w-full appearance-none rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 pr-8 text-xs text-white ring-1 ring-white/10 focus:bg-[#0e0e18] focus:outline-none focus:ring-2 focus:ring-amber-500/60 font-mono">
                    <option value="" class="bg-[#0e0e18] text-white font-sans">Any action</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}" class="bg-[#0e0e18] text-white font-mono" @selected(request('action') === $action)>
                            {{ $action }}
                        </option>
                    @endforeach
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </div>
            </div>

            {{-- Severity Dropdown --}}
            <div class="relative">
                <select name="severity" @change="$el.form.requestSubmit()" class="w-full appearance-none rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 pr-8 text-xs text-white ring-1 ring-white/10 focus:bg-[#0e0e18] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                    <option value="" class="bg-[#0e0e18] text-white">Any severity</option>
                    @foreach(['critical', 'notice', 'info'] as $severity)
                        <option value="{{ $severity }}" class="bg-[#0e0e18] text-white" @selected(request('severity') === $severity)>
                            {{ ucfirst($severity) }}
                        </option>
                    @endforeach
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </div>
            </div>

            {{-- Date From --}}
            <input type="date" name="from" value="{{ request('from') }}" aria-label="From date"
                   class="rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-xs text-white ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-amber-500/60">

            {{-- Date To --}}
            <input type="date" name="to" value="{{ request('to') }}" aria-label="To date"
                   class="rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-xs text-white ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-amber-500/60">

            {{-- Filter Button & Reset --}}
            <div class="flex items-center gap-2">
                <button type="submit"
                        class="flex-1 rounded-xl bg-amber-500 px-4 py-2.5 text-xs font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-[0.98]">
                    Filter
                </button>
                @if(request()->hasAny(['admin', 'action', 'severity', 'from', 'to']))
                    <a href="{{ route('admin.activity.index') }}"
                       class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-xs font-medium text-slate-300 hover:bg-white/[0.08] hover:text-white transition">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Activity Feed Stream --}}
    <div class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 shadow-xl backdrop-blur-xl overflow-hidden">
        <div class="divide-y divide-white/[0.04]">
            @forelse($entries as $entry)
                <div x-data="{ expanded: false }" class="p-5 transition hover:bg-white/[0.02]">
                    <div class="flex items-start gap-4">
                        {{-- Severity Status Indicator Dot --}}
                        <div class="pt-1">
                            @if($entry->severity === 'critical')
                                <span class="flex h-3 w-3 shrink-0 rounded-full bg-rose-400 shadow-[0_0_8px_rgba(251,113,133,0.7)]" title="Critical action"></span>
                            @elseif($entry->severity === 'notice')
                                <span class="flex h-3 w-3 shrink-0 rounded-full bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.6)]" title="Notice action"></span>
                            @else
                                <span class="flex h-3 w-3 shrink-0 rounded-full bg-slate-500" title="Informational action"></span>
                            @endif
                        </div>

                        {{-- Event Body --}}
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-white leading-snug">{{ $entry->description }}</p>
                                <span @class([
                                    'rounded-md px-2 py-0.5 text-[10px] font-mono font-bold uppercase tracking-wider border',
                                    'bg-rose-500/15 text-rose-300 border-rose-500/20' => $entry->severity === 'critical',
                                    'bg-amber-500/15 text-amber-300 border-amber-500/20' => $entry->severity === 'notice',
                                    'bg-slate-500/15 text-slate-400 border-slate-500/20' => $entry->severity === 'info',
                                ])>
                                    {{ $entry->severity }}
                                </span>
                            </div>

                            {{-- Attribution Metadata --}}
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                <span class="font-medium text-slate-200">{{ $entry->admin_email }}</span>
                                <span class="text-slate-600">·</span>
                                <code class="rounded bg-white/[0.04] px-1.5 py-0.5 font-mono text-[11px] text-amber-300/90">{{ $entry->action }}</code>
                                <span class="text-slate-600">·</span>
                                <span>{{ $entry->created_at?->diffForHumans() }}</span>

                                @if($entry->tenant)
                                    <span class="text-slate-600">·</span>
                                    <span>Workspace:</span>
                                    <a href="{{ route('admin.tenants.show', $entry->tenant_id) }}" class="font-medium text-amber-400 hover:text-amber-300 hover:underline">
                                        {{ $entry->tenant->name }}
                                    </a>
                                @endif

                                @if($entry->ip_address)
                                    <span class="text-slate-600">·</span>
                                    <span class="font-mono text-slate-500 text-[11px]">IP: {{ $entry->ip_address }}</span>
                                @endif
                            </div>

                            {{-- Reason Quote --}}
                            @if($entry->reason)
                                <div class="mt-2 rounded-xl border border-white/5 bg-white/[0.02] p-2.5 text-xs italic text-slate-300">
                                    “{{ $entry->reason }}”
                                </div>
                            @endif

                            {{-- Diff / Changes Inspector --}}
                            @if(!empty($entry->changes))
                                <div class="mt-2.5">
                                    <button type="button" @click="expanded = !expanded"
                                            class="inline-flex items-center gap-1.5 text-xs font-mono text-slate-400 hover:text-amber-300 transition">
                                        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="{ 'rotate-90': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                        <span x-text="expanded ? 'Hide change payload' : 'Inspect change payload'">Inspect change payload</span>
                                    </button>

                                    <div x-show="expanded" x-cloak x-collapse class="mt-2">
                                        <pre class="overflow-x-auto rounded-xl border border-white/10 bg-[#07070d] p-3 font-mono text-xs text-slate-300 leading-relaxed">{{ json_encode($entry->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Timestamp Badge --}}
                        <time class="shrink-0 font-mono text-xs text-slate-500" datetime="{{ $entry->created_at?->toIso8601String() }}">
                            {{ $entry->created_at?->format('M j, H:i') }}
                        </time>
                    </div>
                </div>
            @empty
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.02] text-slate-500">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-white">No activity matched</h3>
                    <p class="mt-1 text-sm text-slate-400">Operator actions appear here the moment they happen.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Pagination --}}
    @if($entries->hasPages())
        <div class="mt-6">
            {{ $entries->links() }}
        </div>
    @endif
</x-layouts.admin>
