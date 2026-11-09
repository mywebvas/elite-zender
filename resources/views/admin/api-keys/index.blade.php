<x-layouts.admin title="API keys" subtitle="Platform-level keys for the operator API.">
    {{-- Header Action & Security Bar --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold tracking-tight text-white">Operator API Keys</h2>
                    <span class="inline-flex items-center rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-0.5 text-xs font-mono font-medium text-slate-300">
                        {{ $keys->count() }} registered
                    </span>
                </div>
                <p class="text-xs text-slate-400">Scoped, IP-restricted administrative credentials stored as SHA-256 hashes.</p>
            </div>
        </div>

        @if(session('new_api_key'))
            <div class="flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2.5 text-xs text-emerald-200 shadow-lg">
                <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="font-mono">
                    <span class="text-slate-400">Generated Plaintext Key:</span>
                    <strong class="ml-1 text-white select-all">{{ session('new_api_key') }}</strong>
                </div>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3">
        {{-- Left 2 Columns: Issued Keys List --}}
        <div class="xl:col-span-2 space-y-6">
            <div class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 shadow-xl backdrop-blur-xl overflow-hidden">
                <div class="border-b border-white/[0.08] bg-white/[0.02] px-6 py-4">
                    <h2 class="text-base font-semibold text-white">Issued keys</h2>
                    <p class="text-xs text-slate-400">Tokens with active or historical access to the operator JSON API.</p>
                </div>

                <div class="divide-y divide-white/[0.04]">
                    @forelse($keys as $key)
                        <div class="p-6 transition hover:bg-white/[0.02]">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1 space-y-2">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <h3 class="text-sm font-bold text-white">{{ $key->name }}</h3>
                                        <span @class([
                                            'rounded-full px-2.5 py-0.5 text-[10px] font-mono font-bold uppercase tracking-wider border',
                                            'bg-emerald-500/15 text-emerald-300 border-emerald-500/20' => $key->status() === 'active',
                                            'bg-rose-500/15 text-rose-300 border-rose-500/20' => $key->status() === 'revoked',
                                            'bg-amber-500/15 text-amber-300 border-amber-500/20' => $key->status() === 'expired',
                                        ])>
                                            {{ $key->status() }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs text-slate-400 select-all">{{ $key->prefix }}••••••••</span>
                                    </div>

                                    {{-- Abilities Pills --}}
                                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                        @foreach($key->abilities ?? [] as $ability)
                                            <span class="inline-flex items-center rounded-lg border border-white/10 bg-white/[0.03] px-2 py-0.5 text-[11px] font-mono text-slate-300">
                                                {{ $abilities[$ability] ?? $ability }}
                                            </span>
                                        @endforeach
                                    </div>

                                    {{-- Metadata line --}}
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 font-mono pt-1">
                                        <span>Created {{ $key->created_at->diffForHumans() }}{{ $key->creator ? ' by '.$key->creator->name : '' }}</span>
                                        <span>·</span>
                                        <span>{{ $key->last_used_at ? 'last used '.$key->last_used_at->diffForHumans() : 'never used' }}</span>
                                        @if($key->expires_at)
                                            <span>·</span>
                                            <span class="text-amber-400/90">expires {{ $key->expires_at->toFormattedDateString() }}</span>
                                        @endif
                                        @if($key->allowed_ips)
                                            <span>·</span>
                                            <span class="rounded bg-white/[0.05] px-1.5 py-0.5 text-slate-400">restricted to {{ count($key->allowed_ips) }} IP(s)</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Revoke Action --}}
                                @if($key->status() === 'active')
                                    <div class="shrink-0 pt-1">
                                        <form method="POST" action="{{ route('admin.api-keys.destroy', $key->id) }}"
                                              onsubmit="return confirm('Revoke “{{ $key->name }}”? Any request using it fails immediately.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-xl border border-rose-500/30 bg-rose-500/10 px-3.5 py-1.5 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/20 active:scale-[0.98]">
                                                <svg class="h-3.5 w-3.5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                                <span>Revoke</span>
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.02] text-slate-500">
                                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-semibold text-white">No API keys yet</h3>
                            <p class="mt-1 text-sm text-slate-400">Create one to let an external system read platform metrics or manage workspaces.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right Column: Create API Key Form --}}
        <div>
            <form method="POST" action="{{ route('admin.api-keys.store') }}"
                  class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 p-6 shadow-xl backdrop-blur-xl space-y-5">
                @csrf
                <div class="border-b border-white/[0.08] pb-4">
                    <h2 class="text-base font-semibold text-white">Create a key</h2>
                    <p class="mt-0.5 text-xs text-slate-400">Grant the narrowest set of abilities that does the job.</p>
                </div>

                <div>
                    <label for="name" class="mb-1.5 block text-xs font-medium text-slate-300">Key Name</label>
                    <input id="name" name="name" required maxlength="60" placeholder="e.g. Status page monitor"
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                </div>

                <fieldset>
                    <legend class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Abilities & Scopes</legend>
                    <div class="space-y-2 rounded-xl border border-white/5 bg-white/[0.02] p-3">
                        @foreach($abilities as $value => $label)
                            <label class="flex items-start gap-2.5 text-sm text-slate-300 cursor-pointer select-none">
                                <input type="checkbox" name="abilities[]" value="{{ $value }}"
                                       class="mt-0.5 h-4 w-4 rounded border-white/20 bg-white/[0.06] text-amber-500 focus:ring-amber-500 focus:ring-offset-0">
                                <div>
                                    <span class="font-medium text-white text-xs">{{ $label }}</span>
                                    <code class="ml-1 text-[10px] text-slate-500 font-mono">{{ $value }}</code>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div>
                    <label for="allowed_ips" class="mb-1.5 block text-xs font-medium text-slate-300">
                        IP allow-list <span class="text-slate-500 font-normal">(optional)</span>
                    </label>
                    <input id="allowed_ips" name="allowed_ips" placeholder="203.0.113.4, 198.51.100.7"
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 font-mono text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                    <p class="mt-1 text-[11px] text-slate-500">Comma separated. Leave blank to allow requests from any address.</p>
                </div>

                <div>
                    <label for="expires_in_days" class="mb-1.5 block text-xs font-medium text-slate-300">
                        Expires after <span class="text-slate-500 font-normal">(optional)</span>
                    </label>
                    <input id="expires_in_days" name="expires_in_days" type="number" min="1" max="3650" placeholder="90"
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 font-mono text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                    <p class="mt-1 text-[11px] text-slate-500">Days until expiration. Enforces credential rotation hygiene.</p>
                </div>

                <button type="submit"
                        class="w-full rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-[0.98]">
                    Create key
                </button>
            </form>
        </div>
    </div>
</x-layouts.admin>
