<x-layouts.admin title="Settings" subtitle="Changes take effect immediately — no deploy required.">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        @foreach($groups as $groupKey => $groupLabel)
            @php $fields = array_filter($schema, fn ($m) => $m['group'] === $groupKey); @endphp

            <section class="rounded-xl border border-white/10 bg-[#0b0b14]">
                <div class="flex items-center justify-between border-b border-white/[0.07] px-5 py-3.5">
                    <h2 class="text-sm font-semibold">{{ $groupLabel }}</h2>

                    @if(in_array($groupKey, ['paystack', 'stripe'], true))
                        @php $gateway = $gateways[$groupKey] ?? null; @endphp
                        <span @class([
                            'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                            'bg-emerald-500/15 text-emerald-400' => $gateway?->isConfigured(),
                            'bg-white/[0.06] text-slate-500' => ! $gateway?->isConfigured(),
                        ])>
                            {{ $gateway?->isConfigured() ? 'Live at checkout' : 'Not configured' }}
                        </span>
                    @endif
                </div>

                <div class="space-y-5 p-5">
                    @foreach($fields as $key => $meta)
                        @php $field = str_replace('.', '__', $key); $value = $values[$key] ?? null; @endphp

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:gap-4">
                            <label for="{{ $field }}" class="text-sm font-medium text-slate-300 sm:pt-2">
                                {{ $meta['label'] }}
                            </label>

                            <div class="sm:col-span-2">
                                @if($meta['type'] === 'bool')
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input type="hidden" name="settings[{{ $field }}]" value="0">
                                        <input id="{{ $field }}" type="checkbox" name="settings[{{ $field }}]" value="1"
                                               @checked((bool) $value) class="peer sr-only">
                                        <div class="peer h-6 w-11 rounded-full bg-white/10 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-amber-500 peer-checked:after:translate-x-full peer-focus:ring-2 peer-focus:ring-amber-500/50"></div>
                                    </label>
                                @elseif($meta['type'] === 'secret')
                                    <div class="flex flex-wrap items-center gap-2">
                                        <input id="{{ $field }}" type="password" name="settings[{{ $field }}]"
                                               autocomplete="new-password"
                                               placeholder="{{ $value['set'] ? 'Leave blank to keep the current key' : 'Not set' }}"
                                               class="min-w-0 flex-1 rounded-lg border-0 bg-white/[0.05] px-3 py-2 font-mono text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-amber-500">
                                        @if($value['set'])
                                            <code class="rounded bg-white/[0.04] px-2 py-1.5 font-mono text-xs text-slate-400">{{ $value['preview'] }}</code>
                                        @endif
                                    </div>
                                @else
                                    <input id="{{ $field }}" type="{{ $meta['type'] === 'int' ? 'number' : 'text' }}"
                                           name="settings[{{ $field }}]" value="{{ $value }}"
                                           class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-amber-500">
                                @endif

                                @if($meta['help'])
                                    <p class="mt-1.5 text-xs text-slate-500">{{ $meta['help'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="sticky bottom-4 flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-[#0b0b14]/95 p-4 backdrop-blur-xl">
            <p class="text-xs text-slate-500">
                Anything left blank falls back to the value deployed in <code class="text-slate-400">.env</code>.
            </p>
            <button type="submit" class="rounded-lg bg-amber-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">
                Save settings
            </button>
        </div>
    </form>
</x-layouts.admin>
