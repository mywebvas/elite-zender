<x-layouts.admin title="Plans & Pricing" subtitle="Configure subscription tiers, minor-unit billing prices, and workspace resource quotas.">
    {{-- Top Guidance & Currency Notice --}}
    <div class="mb-6 rounded-2xl border border-amber-500/30 bg-gradient-to-r from-amber-500/[0.08] via-[#0c0c16] to-[#0a0a12] p-5 shadow-xl backdrop-blur-xl">
        <div class="flex items-start gap-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-amber-500/30 bg-amber-500/10 text-amber-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-semibold text-white">Minor Units Billing Engine</h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-300">
                    Prices are stored in integer <strong>minor units</strong> (kobo for NGN, cents for USD) to prevent floating-point rounding errors.
                    For example, <code class="rounded bg-white/10 px-1.5 py-0.5 font-mono text-amber-300">1200000</code> represents <strong>₦12,000.00</strong>, and <code class="rounded bg-white/10 px-1.5 py-0.5 font-mono text-amber-300">1500</code> represents <strong>$15.00</strong>.
                    Leave price empty for custom/quote-only plans. Leave quota limits empty for unlimited access.
                </p>
            </div>
        </div>
    </div>

    {{-- Create Plan Accordion Drawer --}}
    <div x-data="{ creating: false, ngn: '', usd: '' }" class="mb-8">
        <div class="flex items-center justify-between">
            <button type="button" @click="creating = !creating"
                    class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-md transition hover:bg-amber-400 active:scale-[0.98]">
                <svg class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-45': creating }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                <span x-text="creating ? 'Cancel New Plan' : 'Create New Plan'">Create New Plan</span>
            </button>
            <span class="text-xs text-slate-400 font-mono">
                {{ $plans->count() }} registered {{ Str::plural('tier', $plans->count()) }}
            </span>
        </div>

        <form x-show="creating" x-cloak x-collapse method="POST" action="{{ route('admin.plans.store') }}"
              class="mt-4 rounded-2xl border border-amber-500/40 bg-[#0c0c16] p-6 shadow-2xl backdrop-blur-xl">
            @csrf
            <div class="border-b border-white/[0.08] pb-4">
                <h2 class="text-base font-semibold text-white">Create a subscription tier</h2>
                <p class="mt-0.5 text-xs text-slate-400">
                    New tiers start hidden (private) by default so you can verify rates before publishing to customer workspaces.
                </p>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Code --}}
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-300">Plan Code (Identifier)</label>
                    <input name="code" required pattern="[a-z0-9_-]+" placeholder="e.g. agency_tier"
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 font-mono text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                    <p class="mt-1 text-[11px] text-slate-500">Lowercase letters, numbers, dashes, underscores.</p>
                </div>

                {{-- Name --}}
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-300">Display Name</label>
                    <input name="name" required maxlength="60" placeholder="e.g. Agency Pro"
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                </div>

                {{-- Price NGN --}}
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-300">Price NGN (kobo)</label>
                    <input name="price_ngn" type="number" min="0" x-model="ngn" placeholder="e.g. 1200000"
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 font-mono text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                    <div class="mt-1 text-[11px] font-mono text-amber-300">
                        <template x-if="ngn && ngn > 0">
                            <span>Preview: ₦<span x-text="(parseFloat(ngn)/100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span></span>
                        </template>
                        <template x-if="!ngn || ngn === ''">
                            <span class="text-slate-500">Quote-only / Custom</span>
                        </template>
                    </div>
                </div>

                {{-- Price USD --}}
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-300">Price USD (cents)</label>
                    <input name="price_usd" type="number" min="0" x-model="usd" placeholder="e.g. 1500"
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 font-mono text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                    <div class="mt-1 text-[11px] font-mono text-emerald-400">
                        <template x-if="usd && usd > 0">
                            <span>Preview: $<span x-text="(parseFloat(usd)/100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span></span>
                        </template>
                        <template x-if="!usd || usd === ''">
                            <span class="text-slate-500">Quote-only / Custom</span>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Limits Grid --}}
            <div class="mt-5 border-t border-white/[0.06] pt-4">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Resource Quota Limits</span>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach(['contacts' => 'Contacts Quota', 'emails_per_month' => 'Emails / Month', 'smtp_accounts' => 'SMTP Relays', 'users' => 'Team Seats'] as $key => $label)
                        <div>
                            <label class="mb-1 block text-xs text-slate-400">{{ $label }}</label>
                            <input name="limits[{{ $key }}]" type="number" min="0" placeholder="Unlimited"
                                   class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Description & Sort Order --}}
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div class="sm:col-span-3">
                    <label class="mb-1.5 block text-xs font-medium text-slate-300">Plan Description</label>
                    <input name="description" maxlength="255" placeholder="Summary of who this tier is designed for..."
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-300">Sort Order</label>
                    <input name="sort_order" type="number" min="0" max="999" value="0"
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-white/[0.08] pt-4">
                <button type="button" @click="creating = false"
                        class="rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2 text-xs font-semibold text-slate-300 hover:bg-white/[0.08] transition">
                    Cancel
                </button>
                <button type="submit" class="rounded-xl bg-amber-500 px-5 py-2 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-[0.98]">
                    Create Plan
                </button>
            </div>
        </form>
    </div>

    {{-- Plans List --}}
    <div class="space-y-6">
        @foreach($plans as $plan)
            <div x-data="{ ngn: '{{ $plan->price_ngn }}', usd: '{{ $plan->price_usd }}' }"
                 class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 shadow-xl backdrop-blur-xl overflow-hidden transition hover:border-white/20">
                <form method="POST" action="{{ route('admin.plans.update', $plan->id) }}" class="p-6">
                    @csrf
                    @method('PUT')

                    {{-- Plan Header --}}
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-white/[0.08] pb-4">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-lg font-bold text-white">{{ $plan->name }}</h2>
                                <code class="rounded-lg border border-white/10 bg-white/[0.04] px-2 py-0.5 text-xs font-mono text-amber-300">{{ $plan->code }}</code>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                <span class="font-medium text-slate-300">{{ $plan->subscriptions_count }} active subscription(s)</span>
                                <span>·</span>
                                @if(! $plan->is_active)
                                    <span class="inline-flex items-center rounded-full border border-rose-500/30 bg-rose-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-rose-400">Archived</span>
                                @elseif(! $plan->is_public)
                                    <span class="inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-400">Hidden from customers</span>
                                @else
                                    <span class="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-400">Live in catalog</span>
                                @endif
                            </div>
                        </div>

                        {{-- Status Toggles --}}
                        <div class="flex items-center gap-5">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" name="is_active" value="1" @checked($plan->is_active)
                                       class="h-4 w-4 rounded border-white/20 bg-white/[0.06] text-amber-500 focus:ring-amber-500 focus:ring-offset-0">
                                <span class="text-xs font-medium text-slate-300">Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" name="is_public" value="1" @checked($plan->is_public)
                                       class="h-4 w-4 rounded border-white/20 bg-white/[0.06] text-amber-500 focus:ring-amber-500 focus:ring-offset-0">
                                <span class="text-xs font-medium text-slate-300">Public</span>
                            </label>
                        </div>
                    </div>

                    {{-- Form Fields --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {{-- Name --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-slate-300">Display Name</label>
                            <input type="text" name="name" value="{{ $plan->name }}" required maxlength="60"
                                   class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                        </div>

                        {{-- Price NGN with Live Minor Unit Preview --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-slate-300">Price NGN (kobo)</label>
                            <input type="number" name="price_ngn" value="{{ $plan->price_ngn }}" min="0" x-model="ngn"
                                   class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 font-mono text-sm text-white ring-1 ring-white/10 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                            <div class="mt-1 text-[11px] font-mono text-amber-300">
                                <template x-if="ngn && ngn > 0">
                                    <span>Preview: ₦<span x-text="(parseFloat(ngn)/100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span></span>
                                </template>
                                <template x-if="!ngn || ngn === ''">
                                    <span class="text-slate-500">Quote-only / Custom</span>
                                </template>
                            </div>
                        </div>

                        {{-- Price USD with Live Minor Unit Preview --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-slate-300">Price USD (cents)</label>
                            <input type="number" name="price_usd" value="{{ $plan->price_usd }}" min="0" x-model="usd"
                                   class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 font-mono text-sm text-white ring-1 ring-white/10 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                            <div class="mt-1 text-[11px] font-mono text-emerald-400">
                                <template x-if="usd && usd > 0">
                                    <span>Preview: $<span x-text="(parseFloat(usd)/100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span></span>
                                </template>
                                <template x-if="!usd || usd === ''">
                                    <span class="text-slate-500">Quote-only / Custom</span>
                                </template>
                            </div>
                        </div>

                        {{-- Sort Order --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-slate-300">Catalog Sort Order</label>
                            <input type="number" name="sort_order" value="{{ $plan->sort_order }}" min="0" max="999"
                                   class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                        </div>
                    </div>

                    {{-- Limits Grid --}}
                    <div class="mt-5 border-t border-white/[0.06] pt-4">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Resource Quota Limits</span>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach(['contacts' => 'Contacts Quota', 'emails_per_month' => 'Emails / Month', 'smtp_accounts' => 'SMTP Relays', 'users' => 'Team Seats'] as $key => $label)
                                <div>
                                    <label class="mb-1 block text-xs text-slate-400">{{ $label }}</label>
                                    <input type="number" name="limits[{{ $key }}]" value="{{ $plan->limit($key) }}" min="0" placeholder="Unlimited"
                                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Description --}}
                    <div class="mt-4">
                        <label class="mb-1.5 block text-xs font-medium text-slate-300">Description</label>
                        <input type="text" name="description" value="{{ $plan->description }}" maxlength="255"
                               class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                    </div>

                    {{-- Actions Bar --}}
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-white/[0.08] pt-4">
                        <button type="submit" form="archive-{{ $plan->id }}"
                                class="rounded-xl border border-white/5 bg-white/[0.02] px-3.5 py-2 text-xs font-medium text-slate-400 transition hover:border-rose-500/30 hover:bg-rose-500/10 hover:text-rose-300">
                            {{ $plan->subscriptions_count > 0 ? 'Archive plan' : 'Delete plan' }}
                        </button>

                        <button type="submit" class="rounded-xl bg-amber-500 px-5 py-2 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-[0.98]">
                            Save {{ $plan->name }}
                        </button>
                    </div>
                </form>

                {{-- Separate form for Archive/Delete to avoid invalid nested <form> --}}
                <form id="archive-{{ $plan->id }}" method="POST" action="{{ route('admin.plans.destroy', $plan->id) }}" class="hidden"
                      x-data="{ archiving: false }"
                      @submit="if(archiving){ $event.preventDefault(); return false; } if(!confirm('{{ $plan->subscriptions_count > 0
                          ? 'Archive '.$plan->name.'? Existing subscribers keep it; nobody new can choose it.'
                          : 'Delete '.$plan->name.' permanently?' }}')){ return false; } archiving = true;">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="reason" value="Archived from the plans page">
                </form>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
