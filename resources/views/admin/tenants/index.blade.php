<x-layouts.admin title="Workspaces">
    <form method="GET" class="mb-4 flex flex-wrap gap-3">
        <input type="search" name="search" value="{{ $search }}" placeholder="Search workspaces…"
               class="flex-1 rounded-lg border-0 bg-white/5 px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500">
        <select name="status" class="rounded-lg border-0 bg-white/5 px-3 py-2 text-sm text-white ring-1 ring-white/10 focus:ring-2 focus:ring-amber-500">
            <option value="">Any status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
        </select>
        <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-white/10 bg-[#0b0b14]">
        <table class="w-full text-sm">
            <thead class="border-b border-white/10 text-left text-xs uppercase tracking-wider text-slate-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Workspace</th>
                    <th class="px-5 py-3 font-semibold">Users</th>
                    <th class="px-5 py-3 font-semibold">Campaigns</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 font-semibold">Created</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($tenants as $tenant)
                    <tr class="transition hover:bg-white/5">
                        <td class="px-5 py-3 font-medium">{{ $tenant->name }}</td>
                        <td class="px-5 py-3 tabular-nums text-slate-400">{{ $tenant->users_count }}</td>
                        <td class="px-5 py-3 tabular-nums text-slate-400">{{ $tenant->campaigns_count }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $tenant->status === 'active' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">
                                {{ $tenant->status }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-slate-400">{{ $tenant->created_at->toFormattedDateString() }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="text-xs font-semibold text-amber-500 hover:underline">Open →</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No workspaces found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tenants->links() }}</div>
</x-layouts.admin>
