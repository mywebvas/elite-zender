<x-layouts.admin :title="$tenant->name">
    <div class="mb-4">
        <a href="{{ route('admin.tenants.index') }}" class="text-xs text-slate-400 hover:text-white">← All workspaces</a>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">

            <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">{{ $tenant->name }}</h2>
                        <p class="font-mono text-xs text-slate-500">{{ $tenant->id }}</p>
                        <p class="mt-1 text-xs text-slate-400">Created {{ $tenant->created_at->toFormattedDayDateString() }} · {{ $tenant->timezone() }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $tenant->status === 'active' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">
                        {{ $tenant->status }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-4 border-t border-white/10 pt-4">
                    @foreach(['Contacts' => $counts['contacts'], 'Campaigns' => $counts['campaigns'], 'Relays' => $counts['relays']] as $label => $value)
                        <div>
                            <p class="text-xs text-slate-400">{{ $label }}</p>
                            <p class="text-xl font-bold tabular-nums">{{ number_format($value) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Users + impersonation --}}
            <div class="rounded-xl border border-white/10 bg-[#0b0b14]">
                <div class="border-b border-white/10 px-5 py-3">
                    <h2 class="text-sm font-semibold">Users</h2>
                </div>
                @foreach($users as $user)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 px-5 py-3 last:border-0">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }} · {{ $user->role }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.impersonate', $user->id) }}" class="flex items-center gap-2"
                              onsubmit="return confirm('Sign in as {{ $user->email }}? This is recorded and shown to them.')">
                            @csrf
                            <input type="text" name="reason" placeholder="Reason (logged)" maxlength="255"
                                   class="w-44 rounded-lg border-0 bg-white/5 px-2.5 py-1.5 text-xs text-white ring-1 ring-white/10 placeholder:text-slate-500">
                            <button type="submit" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold hover:bg-white/20">
                                Log in as
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>

            {{-- Invoices --}}
            <div class="rounded-xl border border-white/10 bg-[#0b0b14]">
                <div class="border-b border-white/10 px-5 py-3">
                    <h2 class="text-sm font-semibold">Invoices</h2>
                </div>
                @forelse($invoices as $invoice)
                    <div class="flex items-center justify-between border-b border-white/5 px-5 py-3 text-sm last:border-0">
                        <div>
                            <p class="font-mono font-medium">{{ $invoice->number }}</p>
                            <p class="text-xs text-slate-500">{{ $invoice->created_at->toFormattedDateString() }}</p>
                        </div>
                        <div class="text-right">
                            <x-money :amount="$invoice->total" :currency="$invoice->currency" class="font-semibold" />
                            <p class="text-xs text-slate-500">{{ $invoice->status }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">No invoices.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-4">
            {{-- Subscription + plan override --}}
            <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                <h2 class="mb-3 text-sm font-semibold">Subscription</h2>

                @if($subscription)
                    <p class="text-lg font-bold">{{ $subscription->plan?->name ?? '—' }}</p>
                    <p class="text-xs text-slate-400">
                        {{ str_replace('_', ' ', $subscription->status) }}
                        @if($subscription->current_period_end) · renews {{ $subscription->current_period_end->toFormattedDateString() }}@endif
                    </p>
                @else
                    <p class="text-sm text-slate-500">No subscription.</p>
                @endif

                @if(auth('admin')->user()?->canManage())
                    <form method="POST" action="{{ route('admin.tenants.plan', $tenant->id) }}" class="mt-4 space-y-2">
                        @csrf
                        @method('PUT')
                        <label for="plan_id" class="block text-xs text-slate-400">Move to plan (no charge)</label>
                        <select id="plan_id" name="plan_id" required class="w-full rounded-lg border-0 bg-white/5 px-3 py-2 text-sm text-white ring-1 ring-white/10">
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" @selected($subscription?->plan_id === $plan->id)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="reason" placeholder="Reason (logged)" maxlength="255"
                               class="w-full rounded-lg border-0 bg-white/5 px-3 py-2 text-xs text-white ring-1 ring-white/10 placeholder:text-slate-500">
                        <button type="submit" class="w-full rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400">
                            Apply plan
                        </button>
                    </form>
                @endif
            </div>

            {{-- Usage --}}
            <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                <h2 class="mb-3 text-sm font-semibold">Usage</h2>
                @foreach($usage as $key => $row)
                    <div class="mb-3 last:mb-0">
                        <div class="mb-1 flex justify-between text-xs">
                            <span class="text-slate-400">{{ str_replace('_', ' ', $key) }}</span>
                            <span class="tabular-nums">{{ number_format($row['used']) }}{{ $row['limit'] === null ? '' : ' / '.number_format($row['limit']) }}</span>
                        </div>
                        <div class="h-1 w-full overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full {{ ($row['percent'] ?? 0) >= 90 ? 'bg-rose-500' : 'bg-amber-500' }}"
                                 style="width: {{ $row['percent'] ?? 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Status --}}
            @if(auth('admin')->user()?->canManage())
                <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                    <h2 class="mb-1 text-sm font-semibold">Access</h2>
                    <p class="mb-3 text-xs text-slate-500">Suspending blocks sign-in. No data is deleted.</p>
                    <form method="POST" action="{{ route('admin.tenants.status', $tenant->id) }}" class="space-y-2">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="{{ $tenant->status === 'active' ? 'suspended' : 'active' }}">
                        <input type="text" name="reason" placeholder="Reason (logged)" maxlength="255"
                               class="w-full rounded-lg border-0 bg-white/5 px-3 py-2 text-xs text-white ring-1 ring-white/10 placeholder:text-slate-500">
                        <button type="submit"
                                class="w-full rounded-lg px-3 py-2 text-sm font-semibold {{ $tenant->status === 'active' ? 'bg-rose-500/20 text-rose-300 hover:bg-rose-500/30' : 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30' }}">
                            {{ $tenant->status === 'active' ? 'Suspend workspace' : 'Reinstate workspace' }}
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
