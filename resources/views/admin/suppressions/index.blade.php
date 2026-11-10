<x-layouts.admin title="Global Suppressions" subtitle="Cryptographic suppression registry protecting sender reputation across every tenant workspace.">
    {{-- Header Action & Stats Bar --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold tracking-tight text-white">Suppression Engine</h2>
                    <span class="inline-flex items-center rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-0.5 text-xs font-mono font-medium text-slate-300">
                        {{ number_format($total) }} suppressed hashes
                    </span>
                </div>
                <p class="text-xs text-slate-400">Deterministic HMAC hashes ensure GDPR erasure compliance while preventing reputation-damaging re-mailing.</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3">
        {{-- Left 2 Columns: Search Lookup & Recent Stream --}}
        <div class="xl:col-span-2 space-y-6">
            {{-- Instant Address Lookup Card --}}
            <div class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 p-6 shadow-xl backdrop-blur-xl">
                <div class="flex items-center gap-2.5 border-b border-white/[0.08] pb-4">
                    <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <h2 class="text-base font-semibold text-white">Look up an address</h2>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-slate-400">
                    Addresses are stored as a keyed hash so opt-outs survive a GDPR erasure — which means we can confirm
                    whether one specific address is suppressed, but never list them all.
                </p>

                <form method="GET" action="{{ route('admin.suppressions.index') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <input type="email" name="email" value="{{ $email }}" placeholder="Enter recipient email address (e.g. user@domain.com)…" required
                               class="w-full rounded-xl border-0 bg-white/[0.04] py-2.5 pl-10 pr-4 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-500 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                    </div>
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-[0.98]">
                        Check Address
                    </button>
                </form>

                {{-- Query Result Match Container --}}
                @if($match !== null)
                    <div class="mt-6 rounded-xl border border-white/[0.08] bg-white/[0.02] p-4">
                        @forelse($match as $entry)
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/5 py-3 first:pt-0 last:border-0 last:pb-0">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
                                        <p class="text-sm text-white font-medium">
                                            Suppressed
                                            <span class="rounded bg-rose-500/15 px-2 py-0.5 font-mono text-xs font-semibold text-rose-300 border border-rose-500/20">
                                                {{ str_replace('_', ' ', $entry->reason) }}
                                            </span>
                                            <span class="text-slate-400 text-xs">
                                                {{ $entry->tenant_id ? 'for one workspace' : 'platform-wide' }}
                                            </span>
                                        </p>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500 font-mono">Recorded {{ $entry->created_at?->diffForHumans() }}</p>
                                </div>

                                @if(auth('admin')->user()?->canManage())
                                    <form method="POST" action="{{ route('admin.suppressions.destroy', $entry->id) }}"
                                          class="flex flex-wrap items-center gap-2"
                                          x-data="{ removing: false }"
                                          @submit="if(removing){ $event.preventDefault(); return false; } if(!confirm('Un-suppressing this address risks sending blocklists if it previously hard-bounced. Proceed?')){ return false; } removing = true;">
                                        @csrf
                                        @method('DELETE')
                                        <input name="reason" required minlength="5" maxlength="255" placeholder="Reason for removal (required)…"
                                               class="rounded-xl border-0 bg-white/[0.04] px-3 py-1.5 text-xs text-white ring-1 ring-white/10 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-rose-500/50 w-64">
                                        <button type="submit" :disabled="removing"
                                                :class="{ 'opacity-60 cursor-not-allowed': removing }"
                                                class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-1.5 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/20 active:scale-[0.98]">
                                            <span x-text="removing ? 'Removing…' : 'Remove'">Remove</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <div class="flex items-center gap-2.5 py-1 text-sm font-medium text-emerald-400">
                                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Not suppressed — this address can be mailed.</span>
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>

            {{-- Recent Suppressions Stream --}}
            <div class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 shadow-xl backdrop-blur-xl overflow-hidden">
                <div class="border-b border-white/[0.08] bg-white/[0.02] px-6 py-4">
                    <h2 class="text-sm font-semibold text-white">Most recent</h2>
                    <p class="text-xs text-slate-500">Live cryptographic hash entries ingested across relays and webhooks.</p>
                </div>
                <div class="divide-y divide-white/[0.04]">
                    @forelse($recent as $entry)
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between px-6 py-3 transition hover:bg-white/[0.02]">
                            <div class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                                <span class="font-mono text-xs text-slate-400 select-all">{{ Str::limit($entry->email_hash, 28) }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-slate-500 font-mono">
                                <span class="rounded bg-white/[0.04] px-1.5 py-0.5 text-slate-300">{{ str_replace('_', ' ', $entry->reason) }}</span>
                                <span>·</span>
                                <span>{{ $entry->created_at?->diffForHumans() }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-12 text-center text-sm text-slate-500">
                            Nothing suppressed yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right Column: Category Breakdown & Manual Suppression Form --}}
        <div class="space-y-6">
            {{-- Reason Breakdown Card --}}
            <div class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 p-5 shadow-xl backdrop-blur-xl">
                <div class="mb-3 flex items-center justify-between border-b border-white/[0.08] pb-3">
                    <h2 class="text-sm font-semibold text-white">By reason</h2>
                    <span class="text-xs font-mono text-slate-500">Global count</span>
                </div>
                <p class="mb-4 text-3xl font-extrabold text-white tracking-tight tabular-nums font-mono">{{ number_format($total) }}</p>

                <div class="space-y-2.5">
                    @forelse($counts as $reason => $count)
                        <div class="flex items-center justify-between rounded-xl border border-white/5 bg-white/[0.02] p-2.5 text-xs">
                            <span class="text-slate-300 font-medium capitalize">{{ str_replace('_', ' ', $reason) }}</span>
                            <span class="font-mono font-bold text-amber-300 tabular-nums">{{ number_format($count) }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">None yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Manual Suppression Drawer / Form --}}
            @if(auth('admin')->user()?->canManage())
                <form method="POST" action="{{ route('admin.suppressions.store') }}"
                      class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 p-5 shadow-xl backdrop-blur-xl space-y-4">
                    @csrf
                    <div class="border-b border-white/[0.08] pb-3">
                        <h2 class="text-sm font-semibold text-white">Suppress an address</h2>
                        <p class="mt-0.5 text-xs text-slate-400">For complaints or dispute notices that arrive out-of-band via direct email.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-300">Recipient Email</label>
                        <input name="email" type="email" required placeholder="victim@example.com"
                               class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-300">Scope Restriction</label>
                        <select name="tenant_id"
                                class="w-full rounded-xl border-0 bg-[#0e0e18] px-3.5 py-2 text-sm text-white ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                            <option value="">Platform-wide (every workspace)</option>
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }} only</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-300">Audit Reason</label>
                        <input name="reason" maxlength="255" placeholder="e.g. Legal request or direct complaint ticket #402"
                               class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-[0.98]">
                        Suppress Address
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.admin>
