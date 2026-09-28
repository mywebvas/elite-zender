<x-layouts.admin title="Plans & pricing">
    <div class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4">
        <p class="text-sm text-amber-300">
            Prices are in <strong>minor units</strong> — kobo for NGN, cents for USD.
            ₦12,000.00 is <code>1200000</code>; $15.00 is <code>1500</code>.
            Leave a price empty to make the plan quote-only. Leave a limit empty for unlimited.
        </p>
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
                        <p class="text-xs text-slate-400">{{ $plan->subscriptions_count }} active subscription(s)</p>
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

                <div class="mt-4 flex justify-end">
                    <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400">
                        Save {{ $plan->name }}
                    </button>
                </div>
            </form>
        @endforeach
    </div>
</x-layouts.admin>
