<x-layouts.admin title="Audit trail" subtitle="Every operator action, who took it, and why.">
    <form method="GET" class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-6">
        <select name="admin" class="rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10">
            <option value="">Any operator</option>
            @foreach($admins as $admin)
                <option value="{{ $admin->id }}" @selected(request('admin') === $admin->id)>{{ $admin->name }}</option>
            @endforeach
        </select>

        <select name="action" class="rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10">
            <option value="">Any action</option>
            @foreach($actions as $action)
                <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
            @endforeach
        </select>

        <select name="severity" class="rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10">
            <option value="">Any severity</option>
            @foreach(['critical', 'notice', 'info'] as $severity)
                <option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ ucfirst($severity) }}</option>
            @endforeach
        </select>

        <input type="date" name="from" value="{{ request('from') }}" aria-label="From date"
               class="rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10">
        <input type="date" name="to" value="{{ request('to') }}" aria-label="To date"
               class="rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10">

        <div class="flex gap-2">
            <button type="submit" class="flex-1 rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400">Filter</button>
            <a href="{{ route('admin.activity.export', request()->query()) }}"
               class="rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold transition hover:bg-white/20" title="Export this filter as CSV">CSV</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-white/10 bg-[#0b0b14]">
        @forelse($entries as $entry)
            <div class="flex gap-3 border-b border-white/5 px-5 py-3.5 last:border-0">
                <span @class([
                    'mt-1.5 h-2 w-2 flex-shrink-0 rounded-full',
                    'bg-rose-400' => $entry->severity === 'critical',
                    'bg-amber-400' => $entry->severity === 'notice',
                    'bg-slate-600' => $entry->severity === 'info',
                ])></span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm">{{ $entry->description }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        <span class="text-slate-400">{{ $entry->admin_email }}</span>
                        · <code class="text-[11px]">{{ $entry->action }}</code>
                        · {{ $entry->created_at?->diffForHumans() }}
                        @if($entry->tenant)
                            · <a href="{{ route('admin.tenants.show', $entry->tenant_id) }}" class="text-amber-500 hover:underline">{{ $entry->tenant->name }}</a>
                        @endif
                        @if($entry->ip_address) · {{ $entry->ip_address }} @endif
                    </p>
                    @if($entry->reason)
                        <p class="mt-1 border-l-2 border-white/10 pl-2 text-xs italic text-slate-400">“{{ $entry->reason }}”</p>
                    @endif
                </div>

                <time class="flex-shrink-0 text-[11px] tabular-nums text-slate-600" datetime="{{ $entry->created_at?->toIso8601String() }}">
                    {{ $entry->created_at?->format('d M H:i') }}
                </time>
            </div>
        @empty
            <div class="px-5 py-12 text-center">
                <p class="text-sm font-medium">No activity matched</p>
                <p class="mt-1 text-sm text-slate-500">Operator actions appear here the moment they happen.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.admin>
