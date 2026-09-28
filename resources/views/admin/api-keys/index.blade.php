<x-layouts.admin title="API keys" subtitle="Platform-level keys for the operator API.">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <div class="xl:col-span-2">
            <div class="overflow-hidden rounded-xl border border-white/10 bg-[#0b0b14]">
                <div class="border-b border-white/[0.07] px-5 py-3.5">
                    <h2 class="text-sm font-semibold">Issued keys</h2>
                </div>

                @forelse($keys as $key)
                    <div class="flex flex-wrap items-center gap-4 border-b border-white/5 px-5 py-4 last:border-0">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-sm font-medium">{{ $key->name }}</p>
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                                    'bg-emerald-500/15 text-emerald-400' => $key->status() === 'active',
                                    'bg-rose-500/15 text-rose-400' => $key->status() === 'revoked',
                                    'bg-amber-500/15 text-amber-400' => $key->status() === 'expired',
                                ])>{{ $key->status() }}</span>
                            </div>
                            <p class="mt-0.5 font-mono text-xs text-slate-500">{{ $key->prefix }}••••••••</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ implode(' · ', array_map(fn ($a) => $abilities[$a] ?? $a, $key->abilities ?? [])) }}
                            </p>
                            <p class="mt-1 text-[11px] text-slate-600">
                                Created {{ $key->created_at->diffForHumans() }}{{ $key->creator ? ' by '.$key->creator->name : '' }}
                                · {{ $key->last_used_at ? 'last used '.$key->last_used_at->diffForHumans() : 'never used' }}
                                @if($key->expires_at) · expires {{ $key->expires_at->toFormattedDateString() }} @endif
                                @if($key->allowed_ips) · restricted to {{ count($key->allowed_ips) }} IP(s) @endif
                            </p>
                        </div>

                        @if($key->status() === 'active')
                            <form method="POST" action="{{ route('admin.api-keys.destroy', $key->id) }}"
                                  onsubmit="return confirm('Revoke “{{ $key->name }}”? Any request using it fails immediately.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-lg bg-rose-500/15 px-3 py-1.5 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/25">
                                    Revoke
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <p class="text-sm font-medium">No API keys yet</p>
                        <p class="mt-1 text-sm text-slate-500">Create one to let an external system read platform metrics or manage workspaces.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div>
            <form method="POST" action="{{ route('admin.api-keys.store') }}" class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                @csrf
                <h2 class="text-sm font-semibold">Create a key</h2>
                <p class="mt-0.5 text-xs text-slate-500">Grant the narrowest set of abilities that does the job.</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="name" class="mb-1 block text-xs font-medium text-slate-400">Name</label>
                        <input id="name" name="name" required maxlength="60" placeholder="Status page"
                               class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-amber-500">
                    </div>

                    <fieldset>
                        <legend class="mb-1.5 text-xs font-medium text-slate-400">Abilities</legend>
                        <div class="space-y-1.5">
                            @foreach($abilities as $value => $label)
                                <label class="flex items-start gap-2 text-sm text-slate-300">
                                    <input type="checkbox" name="abilities[]" value="{{ $value }}"
                                           class="mt-0.5 rounded border-white/20 bg-white/5 text-amber-500 focus:ring-amber-500">
                                    <span>{{ $label }}<code class="ml-1 text-[10px] text-slate-600">{{ $value }}</code></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div>
                        <label for="allowed_ips" class="mb-1 block text-xs font-medium text-slate-400">
                            IP allow-list <span class="text-slate-600">(optional)</span>
                        </label>
                        <input id="allowed_ips" name="allowed_ips" placeholder="203.0.113.4, 198.51.100.7"
                               class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-amber-500">
                        <p class="mt-1 text-[11px] text-slate-600">Comma separated. Leave blank to allow any address.</p>
                    </div>

                    <div>
                        <label for="expires_in_days" class="mb-1 block text-xs font-medium text-slate-400">
                            Expires after <span class="text-slate-600">(optional)</span>
                        </label>
                        <input id="expires_in_days" name="expires_in_days" type="number" min="1" max="3650" placeholder="90"
                               class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-amber-500">
                        <p class="mt-1 text-[11px] text-slate-600">Days. A key that never expires is a key nobody ever rotates.</p>
                    </div>

                    <button type="submit" class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">
                        Create key
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
