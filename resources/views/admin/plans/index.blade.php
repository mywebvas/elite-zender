<x-layouts.admin title="Plans & pricing" subtitle="A wrong number here bills every customer incorrectly.">
    <div class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4">
        <p class="text-sm text-amber-300">
            Prices are in <strong>minor units</strong> — kobo for NGN, cents for USD.
            ₦12,000.00 is <code>1200000</code>; $15.00 is <code>1500</code>.
            Leave a price empty to make the plan quote-only. Leave a limit empty for unlimited.
        </p>
    </div>

    <div x-data="{ creating: false }" class="mb-4">
        <button type="button" @click="creating = !creating"
                class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/20">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New plan
        </button>

        <form x-show="creating" x-cloak x-collapse method="POST" action="{{ route('admin.plans.store') }}"
              class="mt-3 rounded-xl border border-amber-500/30 bg-[#0b0b14] p-5">
            @csrf
            <h2 class="text-sm font-semibold">Create a plan</h2>
            <p class="mt-0.5 text-xs text-slate-500">
                It starts hidden so you can set pricing and check it before anyone can choose it.
            </p>

            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Code</label>
                    <input name="code" required pattern="[a-z0-9_-]+" placeholder="agency"
                           class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 font-mono text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Name</label>
                    <input name="name" required maxlength="60" placeholder="Agency"
                           class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Price NGN (kobo)</label>
                    <input name="price_ngn" type="number" min="0" placeholder="Leave blank to quote"
                           class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Price USD (cents)</label>
                    <input name="price_usd" type="number" min="0" placeholder="Leave blank to quote"
                           class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600">
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach(['contacts' => 'Contacts', 'emails_per_month' => 'Emails / month', 'smtp_accounts' => 'SMTP relays', 'users' => 'Team members'] as $key => $label)
                    <div>
                        <label class="mb-1 block text-xs text-slate-400">{{ $label }}</label>
                        <input name="limits[{{ $key }}]" type="number" min="0" placeholder="Unlimited"
                               class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600">
                    </div>
                @endforeach
            </div>

            <div class="mt-3">
                <label class="mb-1 block text-xs text-slate-400">Description</label>
                <input name="description" maxlength="255" placeholder="Who is this plan for?"
                       class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600">
            </div>

            <div class="mt-4 flex justify-end">
                <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400">
                    Create plan
                </button>
            </div>
        </form>
    </div>

    <div class="space-y-4">
        @foreach($plans as $plan)
            <form method="POST" action="{{ route('admin.plans.update', $plan->id) }}"
                  class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                @csrf
                @method('PUT')

                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="font-bold">{{ $plan->name }} <code class="ml-1 text-xs text-slate-500">{{ $plan->code }}</code></h2>
                        <p class="text-xs text-slate-400">
                            {{ $plan->subscriptions_count }} active subscription(s)
                            @unless($plan->is_active) · <span class="text-rose-400">archived</span>
                            @elseunless($plan->is_public) · <span class="text-amber-400">hidden from customers</span>
                            @endunless
                        </p>
                    </div>
                    <div class="flex items-center gap-4 text-xs">
                        <label class="flex items-center gap-1.5">
                            <input type="checkbox" name="is_active" value="1" @checked($plan->is_active) class="rounded border-white/20 bg-white/5 text-amber-500">
                            Active
                        </label>
                        <label class="flex items-center gap-1.5">
                            <input type="checkbox" name="is_public" value="1" @checked($plan->is_public) class="rounded border-white/20 bg-white/5 text-amber-500">
                            Public
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs text-slate-400">Name</label>
                        <input type="text" name="name" value="{{ $plan->name }}" required maxlength="60"
                               class="w-full rounded-lg border-0 bg-white/5 px-3 py-2 text-sm text-white ring-1 ring-white/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-400">Price NGN (kobo)</label>
                        <input type="number" name="price_ngn" value="{{ $plan->price_ngn }}" min="0"
                               class="w-full rounded-lg border-0 bg-white/5 px-3 py-2 text-sm text-white ring-1 ring-white/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-400">Price USD (cents)</label>
                        <input type="number" name="price_usd" value="{{ $plan->price_usd }}" min="0"
                               class="w-full rounded-lg border-0 bg-white/5 px-3 py-2 text-sm text-white ring-1 ring-white/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-400">Sort order</label>
                        <input type="number" name="sort_order" value="{{ $plan->sort_order }}" min="0" max="999"
                               class="w-full rounded-lg border-0 bg-white/5 px-3 py-2 text-sm text-white ring-1 ring-white/10">
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach(['contacts' => 'Contacts', 'emails_per_month' => 'Emails / month', 'smtp_accounts' => 'SMTP relays', 'users' => 'Team members'] as $key => $label)
                        <div>
                            <label class="mb-1 block text-xs text-slate-400">{{ $label }}</label>
                            <input type="number" name="limits[{{ $key }}]" value="{{ $plan->limit($key) }}" min="0" placeholder="Unlimited"
                                   class="w-full rounded-lg border-0 bg-white/5 px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600">
                        </div>
                    @endforeach
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-xs text-slate-400">Description</label>
                    <input type="text" name="description" value="{{ $plan->description }}" maxlength="255"
                           class="w-full rounded-lg border-0 bg-white/5 px-3 py-2 text-sm text-white ring-1 ring-white/10">
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <button type="submit" form="archive-{{ $plan->id }}"
                            class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-500 transition hover:bg-rose-500/10 hover:text-rose-300">
                        {{ $plan->subscriptions_count > 0 ? 'Archive plan' : 'Delete plan' }}
                    </button>

                    <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400">
                        Save {{ $plan->name }}
                    </button>
                </div>
            </form>

            {{-- Separate form: nested <form> is invalid HTML. --}}
            <form id="archive-{{ $plan->id }}" method="POST" action="{{ route('admin.plans.destroy', $plan->id) }}" class="hidden"
                  onsubmit="return confirm('{{ $plan->subscriptions_count > 0
                      ? 'Archive '.$plan->name.'? Existing subscribers keep it; nobody new can choose it.'
                      : 'Delete '.$plan->name.' permanently?' }}')">
                @csrf @method('DELETE')
                <input type="hidden" name="reason" value="Archived from the plans page">
            </form>
        @endforeach
    </div>
</x-layouts.admin>
