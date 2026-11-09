<x-layouts.admin title="Settings" subtitle="Changes take effect immediately — no deploy required.">
    {{-- Header Action & Warning Bar --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold tracking-tight text-white">Platform Control Registry</h2>
                    <span class="inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-0.5 text-xs font-mono font-bold text-amber-300">
                        Super Admin Only
                    </span>
                </div>
                <p class="text-xs text-slate-400">Runtime environment overrides for payments, security parameters, and platform branding.</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-4xl space-y-8 pb-16">
        @csrf
        @method('PUT')

        @foreach($groups as $groupKey => $groupLabel)
            @php $fields = array_filter($schema, fn ($m) => $m['group'] === $groupKey); @endphp

            <section class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 shadow-xl backdrop-blur-xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-white/[0.08] bg-white/[0.02] px-6 py-4">
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-base font-semibold text-white">{{ $groupLabel }}</h2>
                    </div>

                    @if(in_array($groupKey, ['paystack', 'stripe'], true))
                        @php $gateway = $gateways[$groupKey] ?? null; @endphp
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-[10px] font-mono font-bold uppercase tracking-wider',
                            'bg-emerald-500/15 text-emerald-400 border border-emerald-500/20' => $gateway?->isConfigured(),
                            'bg-white/[0.06] text-slate-400 border border-white/10' => ! $gateway?->isConfigured(),
                        ])>
                            {{ $gateway?->isConfigured() ? 'Live at checkout' : 'Not configured' }}
                        </span>
                    @endif
                </div>

                <div class="divide-y divide-white/[0.04] p-6 space-y-6">
                    @foreach($fields as $key => $meta)
                        @php $field = str_replace('.', '__', $key); $value = $values[$key] ?? null; @endphp

                        <div class="grid grid-cols-1 gap-2 pt-6 first:pt-0 sm:grid-cols-3 sm:gap-6">
                            <div class="sm:pt-1">
                                <label for="{{ $field }}" class="block text-sm font-medium text-white">
                                    {{ $meta['label'] }}
                                </label>
                                <code class="mt-1 block text-[11px] font-mono text-slate-500">{{ $key }}</code>
                            </div>

                            <div class="sm:col-span-2 space-y-2">
                                @if($meta['type'] === 'bool')
                                    <div class="flex items-center gap-3">
                                        <input type="hidden" name="settings[{{ $field }}]" value="0">
                                        <label class="relative inline-flex cursor-pointer items-center">
                                            <input id="{{ $field }}" type="checkbox" name="settings[{{ $field }}]" value="1"
                                                   @checked((bool) $value) class="peer sr-only">
                                            <div class="peer h-6 w-11 rounded-full bg-white/10 border border-white/10 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-amber-500 peer-checked:border-amber-400 peer-checked:after:translate-x-full peer-focus:ring-2 peer-focus:ring-amber-500/50"></div>
                                        </label>
                                        <span class="text-xs text-slate-400">
                                            {{ (bool) $value ? 'Enabled' : 'Disabled' }}
                                        </span>
                                    </div>
                                @elseif($meta['type'] === 'secret')
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="relative flex-1 min-w-[240px]">
                                                <input id="{{ $field }}" type="password" name="settings[{{ $field }}]"
                                                       autocomplete="new-password"
                                                       placeholder="{{ $value['set'] ? 'Leave blank to keep the current key' : 'Not set' }}"
                                                       class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 font-mono text-sm text-white ring-1 ring-white/10 placeholder:text-slate-500 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                                            </div>
                                            @if($value['set'])
                                                <code class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2 font-mono text-xs text-amber-300">
                                                    {{ $value['preview'] }}
                                                </code>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <input id="{{ $field }}" type="{{ $meta['type'] === 'int' ? 'number' : 'text' }}"
                                           name="settings[{{ $field }}]" value="{{ $value }}"
                                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-500 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                                @endif

                                @if($meta['help'])
                                    <p class="text-xs leading-relaxed text-slate-400">{{ $meta['help'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        {{-- Sticky Bottom Action Bar --}}
        <div class="sticky bottom-6 z-20 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-2xl border border-white/[0.12] bg-[#0c0c16]/95 p-4 shadow-2xl backdrop-blur-xl">
            <p class="text-xs text-slate-400">
                Values left empty automatically inherit the environment variables defined in <code class="rounded bg-white/10 px-1 py-0.5 text-slate-300 font-mono">.env</code>.
            </p>
            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-slate-950 shadow-md transition hover:bg-amber-400 active:scale-[0.98]">
                <span>Save settings</span>
            </button>
        </div>
    </form>
</x-layouts.admin>
