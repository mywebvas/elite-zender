<x-layouts.admin title="Workspaces" subtitle="Platform directory of every customer workspace.">

    {{-- Filter & Search Toolbar --}}
    <div class="mb-6 rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.tenants.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="search" name="search" value="{{ $search }}" placeholder="Search by workspace name…"
                       class="w-full rounded-xl border-0 bg-white/[0.04] pl-10 pr-4 py-2.5 text-xs text-white ring-1 ring-white/10 placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500 transition">
            </div>

            <div class="flex items-center gap-2">
                <select name="status"
                        class="rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-xs font-medium text-white ring-1 ring-white/10 focus:ring-2 focus:ring-amber-500 transition">
                    <option value="" class="bg-[#0c0c16]">Any status</option>
                    <option value="active" @selected(request('status') === 'active') class="bg-[#0c0c16]">Active only</option>
                    <option value="suspended" @selected(request('status') === 'suspended') class="bg-[#0c0c16]">Suspended only</option>
                </select>

                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 px-4 py-2.5 text-xs font-bold text-slate-950 transition hover:bg-amber-400 active:scale-95 shadow-sm">
                    <span>Filter</span>
                </button>

                @if($search !== '' || request()->filled('status'))
                    <a href="{{ route('admin.tenants.index') }}"
                       class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-xs font-semibold text-slate-400 hover:text-white transition">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Desktop Table View (Hidden on mobile) --}}
    <div class="hidden lg:block overflow-hidden rounded-2xl border border-white/[0.08] bg-[#0c0c16] shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-white/[0.08] bg-white/[0.02] text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5 font-bold">Workspace</th>
                        <th class="px-5 py-3.5 font-bold">Members</th>
                        <th class="px-5 py-3.5 font-bold">Campaigns</th>
                        <th class="px-5 py-3.5 font-bold">Status</th>
                        <th class="px-5 py-3.5 font-bold">Created</th>
                        <th class="px-5 py-3.5 text-right font-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.04]">
                    @forelse($tenants as $tenant)
                        <tr class="transition hover:bg-white/[0.02]">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-amber-500/10 font-bold text-amber-300 ring-1 ring-amber-500/20 text-xs">
                                        {{ Str::substr($tenant->name, 0, 1) }}
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="font-bold text-sm text-white hover:text-amber-300 transition">
                                            {{ $tenant->name }}
                                        </a>
                                        <p class="font-mono text-[10px] text-slate-500">{{ Str::limit($tenant->id, 18) }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 tabular-nums font-semibold text-slate-300">
                                {{ number_format($tenant->users_count) }} user(s)
                            </td>
                            <td class="px-5 py-4 tabular-nums font-semibold text-slate-300">
                                {{ number_format($tenant->campaigns_count) }} campaign(s)
                            </td>
                            <td class="px-5 py-4">
                                <span @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                    'bg-emerald-500/15 text-emerald-400 border border-emerald-500/25' => $tenant->status === 'active',
                                    'bg-rose-500/15 text-rose-400 border border-rose-500/25' => $tenant->status !== 'active',
                                ])>
                                    <span @class([
                                        'h-1.5 w-1.5 rounded-full',
                                        'bg-emerald-400' => $tenant->status === 'active',
                                        'bg-rose-400' => $tenant->status !== 'active',
                                    ])></span>
                                    <span>{{ $tenant->status }}</span>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-400 text-xs">
                                {{ $tenant->created_at->toFormattedDateString() }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('admin.tenants.show', $tenant->id) }}"
                                   class="inline-flex items-center gap-1 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-1.5 text-xs font-bold text-slate-300 hover:border-amber-500/40 hover:bg-amber-500/10 hover:text-amber-300 transition">
                                    <span>Inspect</span>
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                <p class="text-sm font-medium text-slate-400">No workspaces found</p>
                                <p class="mt-1 text-xs">Try adjusting your search criteria or status filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile Card View (Shown on screens < lg) --}}
    <div class="lg:hidden space-y-3">
        @forelse($tenants as $tenant)
            <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="font-bold text-sm text-white hover:text-amber-300 transition block truncate">
                            {{ $tenant->name }}
                        </a>
                        <p class="font-mono text-[10px] text-slate-500 mt-0.5">{{ Str::limit($tenant->id, 22) }}</p>
                    </div>
                    <span @class([
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider flex-shrink-0',
                        'bg-emerald-500/15 text-emerald-400 border border-emerald-500/25' => $tenant->status === 'active',
                        'bg-rose-500/15 text-rose-400 border border-rose-500/25' => $tenant->status !== 'active',
                    ])>
                        {{ $tenant->status }}
                    </span>
                </div>

                <div class="mt-3.5 grid grid-cols-2 gap-2 border-t border-white/[0.04] pt-3 text-xs">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-500 block">Members</span>
                        <span class="font-semibold text-slate-200 tabular-nums">{{ number_format($tenant->users_count) }} user(s)</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-500 block">Campaigns</span>
                        <span class="font-semibold text-slate-200 tabular-nums">{{ number_format($tenant->campaigns_count) }} built</span>
                    </div>
                </div>

                <div class="mt-3.5 flex items-center justify-between border-t border-white/[0.04] pt-3">
                    <span class="text-[11px] text-slate-500">Joined {{ $tenant->created_at->toFormattedDateString() }}</span>
                    <a href="{{ route('admin.tenants.show', $tenant->id) }}"
                       class="inline-flex items-center gap-1 rounded-xl bg-amber-500/10 border border-amber-500/30 px-3 py-1.5 text-xs font-bold text-amber-300">
                        <span>Inspect</span>
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-8 text-center text-slate-500">
                <p class="text-sm font-medium text-slate-400">No workspaces matched</p>
                <p class="mt-1 text-xs">Try clearing the search query or status filter.</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination Controls --}}
    @if($tenants->hasPages())
        <div class="mt-6">
            {{ $tenants->links() }}
        </div>
    @endif

</x-layouts.admin>
