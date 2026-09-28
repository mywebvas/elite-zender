<x-layouts.admin title="Suppressions" subtitle="Addresses no workspace is allowed to mail.">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <div class="space-y-4 xl:col-span-2">
            <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                <h2 class="text-sm font-semibold">Look up an address</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Addresses are stored as a keyed hash so opt-outs survive a GDPR erasure — which means we can confirm
                    whether one specific address is suppressed, but never list them all.
                </p>

                <form method="GET" class="mt-4 flex flex-wrap gap-2">
                    <input type="email" name="email" value="{{ $email }}" placeholder="someone@example.com" required
                           class="min-w-0 flex-1 rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-amber-500">
                    <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400">Check</button>
                </form>

                @if($match !== null)
                    <div class="mt-4 border-t border-white/[0.07] pt-4">
                        @forelse($match as $entry)
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 py-2.5 last:border-0">
                                <div>
                                    <p class="text-sm">
                                        Suppressed
                                        <span class="font-medium text-rose-300">{{ str_replace('_', ' ', $entry->reason) }}</span>
                                        {{ $entry->tenant_id ? 'for one workspace' : 'platform-wide' }}
                                    </p>
                                    <p class="text-xs text-slate-500">{{ $entry->created_at?->diffForHumans() }}</p>
                                </div>

                                @if(auth('admin')->user()?->canManage())
                                    <form method="POST" action="{{ route('admin.suppressions.destroy', $entry->id) }}" class="flex gap-2">
                                        @csrf @method('DELETE')
                                        <input name="reason" required minlength="5" maxlength="255" placeholder="Reason (required)"
                                               class="w-52 rounded-lg border-0 bg-white/[0.05] px-2.5 py-1.5 text-xs text-white ring-1 ring-white/10 placeholder:text-slate-600">
                                        <button type="submit" class="rounded-lg bg-rose-500/15 px-3 py-1.5 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/25">
                                            Remove
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-emerald-400">Not suppressed — this address can be mailed.</p>
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="overflow-hidden rounded-xl border border-white/10 bg-[#0b0b14]">
                <div class="border-b border-white/[0.07] px-5 py-3.5">
                    <h2 class="text-sm font-semibold">Most recent</h2>
                </div>
                @forelse($recent as $entry)
                    <div class="flex items-center justify-between border-b border-white/5 px-5 py-2.5 text-sm last:border-0">
                        <span class="font-mono text-xs text-slate-500">{{ Str::limit($entry->email_hash, 24) }}</span>
                        <span class="text-xs text-slate-400">{{ str_replace('_', ' ', $entry->reason) }} · {{ $entry->created_at?->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-500">Nothing suppressed yet.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                <h2 class="mb-3 text-sm font-semibold">By reason</h2>
                <p class="mb-3 text-2xl font-bold tabular-nums">{{ number_format($total) }}</p>
                @forelse($counts as $reason => $count)
                    <div class="flex items-center justify-between py-1 text-sm">
                        <span class="text-slate-400">{{ str_replace('_', ' ', $reason) }}</span>
                        <span class="font-semibold tabular-nums">{{ number_format($count) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">None yet.</p>
                @endforelse
            </div>

            @if(auth('admin')->user()?->canManage())
                <form method="POST" action="{{ route('admin.suppressions.store') }}" class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                    @csrf
                    <h2 class="text-sm font-semibold">Suppress an address</h2>
                    <p class="mt-0.5 text-xs text-slate-500">For complaints that arrive by email rather than a feedback loop.</p>

                    <div class="mt-4 space-y-3">
                        <input name="email" type="email" required placeholder="someone@example.com"
                               class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-amber-500">
                        <select name="tenant_id" class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10">
                            <option value="">Platform-wide (every workspace)</option>
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }} only</option>
                            @endforeach
                        </select>
                        <input name="reason" maxlength="255" placeholder="Reason (optional)"
                               class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600">
                        <button type="submit" class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">
                            Suppress
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-layouts.admin>
