<x-layouts.admin :title="$tenant->name" :subtitle="'Workspace ID: ' . $tenant->id">

    {{-- Breadcrumb Navigation --}}
    <div class="mb-5 flex items-center justify-between">
        <a href="{{ route('admin.tenants.index') }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white transition">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            <span>Back to Workspaces</span>
        </a>

        <div class="flex items-center gap-2">
            <span @class([
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider',
                'bg-emerald-500/15 text-emerald-400 border border-emerald-500/25' => $tenant->status === 'active',
                'bg-rose-500/15 text-rose-400 border border-rose-500/25' => $tenant->status !== 'active',
            ])>
                <span @class([
                    'h-1.5 w-1.5 rounded-full',
                    'bg-emerald-400' => $tenant->status === 'active',
                    'bg-rose-400' => $tenant->status !== 'active',
                ])></span>
                <span>{{ $tenant->status }}</span>
            </span>
        </div>
    </div>

    {{-- Top Workspace Identity Banner --}}
    <div class="mb-6 rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-amber-500/15 font-black text-amber-300 ring-1 ring-amber-500/30 text-lg shadow-inner">
                    {{ Str::substr($tenant->name, 0, 1) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-black text-white tracking-tight">{{ $tenant->name }}</h1>
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-400">
                        <div class="flex items-center gap-1 font-mono" x-data="{ copied: false }">
                            <span class="text-slate-500">{{ $tenant->id }}</span>
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $tenant->id }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="p-0.5 text-slate-500 hover:text-amber-300 transition" title="Copy UUID">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                            <span x-show="copied" x-cloak class="text-[10px] font-bold text-amber-300">Copied!</span>
                        </div>
                        <span>·</span>
                        <span>Timezone: {{ $tenant->timezone() }}</span>
                        <span>·</span>
                        <span>Created {{ $tenant->created_at->toFormattedDayDateString() }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- KPI Quick Counters --}}
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 border-t border-white/[0.06] pt-5">
            <div class="rounded-xl border border-white/[0.04] bg-white/[0.02] p-3.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Contacts</span>
                <p class="mt-1 text-xl font-black tabular-nums text-white">{{ number_format($counts['contacts']) }}</p>
            </div>
            <div class="rounded-xl border border-white/[0.04] bg-white/[0.02] p-3.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Campaigns</span>
                <p class="mt-1 text-xl font-black tabular-nums text-white">{{ number_format($counts['campaigns']) }}</p>
            </div>
            <div class="rounded-xl border border-white/[0.04] bg-white/[0.02] p-3.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">SMTP Relays</span>
                <p class="mt-1 text-xl font-black tabular-nums text-white">{{ number_format($counts['relays']) }}</p>
            </div>
            <div class="rounded-xl border border-white/[0.04] bg-white/[0.02] p-3.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Active Plan</span>
                <p class="mt-1 text-sm font-black text-amber-300 truncate">{{ $subscription->plan?->name ?? 'Free Tier' }}</p>
            </div>
        </div>
    </div>

    {{-- Multi-Tab Interface --}}
    <div x-data="{ activeTab: 'overview' }" class="space-y-6">
        {{-- Navigation Tabs --}}
        <div class="flex items-center gap-1.5 border-b border-white/[0.08] pb-px overflow-x-auto scrollbar-none">
            <button type="button" @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'border-amber-400 text-amber-300 bg-amber-500/[0.08]' : 'border-transparent text-slate-400 hover:text-white'"
                    class="rounded-t-xl border-b-2 px-4 py-2.5 text-xs font-bold transition flex items-center gap-2 whitespace-nowrap">
                <span>Overview & Usage</span>
            </button>
            <button type="button" @click="activeTab = 'users'"
                    :class="activeTab === 'users' ? 'border-amber-400 text-amber-300 bg-amber-500/[0.08]' : 'border-transparent text-slate-400 hover:text-white'"
                    class="rounded-t-xl border-b-2 px-4 py-2.5 text-xs font-bold transition flex items-center gap-2 whitespace-nowrap">
                <span>Team & Users ({{ count($users) }})</span>
            </button>
            <button type="button" @click="activeTab = 'invoices'"
                    :class="activeTab === 'invoices' ? 'border-amber-400 text-amber-300 bg-amber-500/[0.08]' : 'border-transparent text-slate-400 hover:text-white'"
                    class="rounded-t-xl border-b-2 px-4 py-2.5 text-xs font-bold transition flex items-center gap-2 whitespace-nowrap">
                <span>Invoices ({{ count($invoices) }})</span>
            </button>
            <button type="button" @click="activeTab = 'lifecycle'"
                    :class="activeTab === 'lifecycle' ? 'border-amber-400 text-amber-300 bg-amber-500/[0.08]' : 'border-transparent text-slate-400 hover:text-white'"
                    class="rounded-t-xl border-b-2 px-4 py-2.5 text-xs font-bold transition flex items-center gap-2 whitespace-nowrap">
                <span>Lifecycle History ({{ count($lifecycleMessages) }})</span>
            </button>
            @if(auth('admin')->user()?->canManage())
                <button type="button" @click="activeTab = 'controls'"
                        :class="activeTab === 'controls' ? 'border-amber-400 text-amber-300 bg-amber-500/[0.08]' : 'border-transparent text-slate-400 hover:text-white'"
                        class="rounded-t-xl border-b-2 px-4 py-2.5 text-xs font-bold transition flex items-center gap-2 whitespace-nowrap">
                    <span>Operational Controls</span>
                </button>
            @endif
        </div>

        {{-- TAB 1: OVERVIEW & USAGE --}}
        <div x-show="activeTab === 'overview'" class="space-y-6">
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    {{-- Usage Gauges --}}
                    <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-5 shadow-sm">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4">Resource Quotas & Utilization</h2>
                        <div class="space-y-4">
                            @foreach($usage as $key => $row)
                                <div>
                                    <div class="mb-1.5 flex justify-between text-xs">
                                        <span class="font-bold text-slate-300 capitalize">{{ str_replace('_', ' ', $key) }}</span>
                                        <span class="font-mono text-slate-400 tabular-nums">
                                            {{ number_format($row['used']) }} / {{ $row['limit'] === null ? 'Unlimited' : number_format($row['limit']) }}
                                        </span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-white/[0.06]">
                                        <div class="h-full rounded-full transition-all duration-500 {{ ($row['percent'] ?? 0) >= 90 ? 'bg-rose-500' : 'bg-amber-500' }}"
                                             style="width: {{ min(100, $row['percent'] ?? 100) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Customer Users Preview --}}
                    <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between border-b border-white/[0.08] px-5 py-4">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Workspace Members</h2>
                            <button type="button" @click="activeTab = 'users'" class="text-xs font-bold text-amber-400 hover:underline">View All →</button>
                        </div>
                        <div class="divide-y divide-white/[0.04]">
                            @foreach($users->take(4) as $user)
                                <div class="flex items-center justify-between p-4 text-xs">
                                    <div class="min-w-0">
                                        <p class="font-bold text-white">{{ $user->name }}</p>
                                        <p class="text-slate-400 font-mono text-[11px]">{{ $user->email }} · <span class="capitalize">{{ $user->role }}</span></p>
                                    </div>
                                    <form method="POST" action="{{ route('admin.impersonate', $user->id) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="reason" value="Support inspection from overview">
                                        <button type="submit" class="rounded-lg border border-white/10 bg-white/[0.04] px-2.5 py-1 text-[11px] font-bold text-slate-300 hover:bg-white/[0.08] transition">
                                            Impersonate
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    {{-- Subscription Summary Card --}}
                    <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-5 shadow-sm">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Subscription Status</h2>
                        @if($subscription)
                            <div class="rounded-xl border border-white/[0.05] bg-white/[0.02] p-4">
                                <p class="text-lg font-black text-white">{{ $subscription->plan?->name ?? 'Custom Plan' }}</p>
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="rounded-full bg-emerald-500/15 border border-emerald-500/25 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-400">
                                        {{ str_replace('_', ' ', $subscription->status) }}
                                    </span>
                                    @if($subscription->current_period_end)
                                        <span class="text-[11px] text-slate-400">Renews {{ $subscription->current_period_end->toFormattedDateString() }}</span>
                                    @endif
                                </div>
                                @if($subscription->cancellation_reason)
                                    <div class="mt-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-200">
                                        <p class="font-bold">Cancellation Pending</p>
                                        <p class="mt-0.5 text-[11px]">{{ \App\Models\Subscription::CANCELLATION_REASONS[$subscription->cancellation_reason] ?? $subscription->cancellation_reason }}</p>
                                        @if($subscription->cancellation_feedback)
                                            <p class="mt-1.5 italic text-[11px] text-amber-300/90">&ldquo;{{ \Illuminate\Support\Str::limit($subscription->cancellation_feedback, 160) }}&rdquo;</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="py-6 text-center text-xs text-slate-500">
                                <p>No active subscription record.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 2: TEAM & USERS --}}
        <div x-show="activeTab === 'users'" class="space-y-4">
            <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] shadow-sm overflow-hidden">
                <div class="border-b border-white/[0.08] px-5 py-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Customer Users in this Workspace</h2>
                </div>
                <div class="divide-y divide-white/[0.04]">
                    @foreach($users as $user)
                        <div class="flex flex-wrap items-center justify-between gap-4 p-5 text-xs transition hover:bg-white/[0.02]">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/[0.04] font-bold text-slate-300">
                                    {{ Str::substr($user->name, 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-white">{{ $user->name }}</p>
                                    <p class="font-mono text-slate-400 text-xs">{{ $user->email }}</p>
                                    <div class="mt-1 flex items-center gap-2 text-[11px] text-slate-500">
                                        <span class="rounded bg-white/[0.06] px-1.5 py-0.5 uppercase font-bold text-slate-400">{{ $user->role }}</span>
                                        <span>·</span>
                                        <span>{{ $user->email_verified_at ? 'Verified email' : 'Unverified' }}</span>
                                        <span>·</span>
                                        <span>Joined {{ $user->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('admin.impersonate', $user->id) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="text" name="reason" placeholder="Reason (audit logged)" required maxlength="255"
                                           class="w-48 rounded-xl border-0 bg-white/[0.04] px-3 py-1.5 text-xs text-white ring-1 ring-white/10 placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500">
                                    <button type="submit"
                                            class="rounded-xl bg-amber-500/15 border border-amber-500/30 px-3.5 py-1.5 text-xs font-bold text-amber-300 hover:bg-amber-500/25 transition">
                                        Log in as
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- TAB 3: INVOICES --}}
        <div x-show="activeTab === 'invoices'" class="space-y-4">
            <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] shadow-sm overflow-hidden">
                <div class="border-b border-white/[0.08] px-5 py-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Billing Invoices History</h2>
                </div>
                <div class="divide-y divide-white/[0.04]">
                    @forelse($invoices as $invoice)
                        <div class="flex items-center justify-between p-4 text-xs">
                            <div class="min-w-0">
                                <p class="font-mono font-bold text-white text-sm">{{ $invoice->number }}</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Issued {{ $invoice->created_at->toFormattedDateString() }}</p>
                            </div>
                            <div class="text-right">
                                <span class="font-black text-sm text-white">
                                    <x-money :amount="$invoice->total" :currency="$invoice->currency" />
                                </span>
                                <span @class([
                                    'block mt-0.5 text-[10px] font-bold uppercase tracking-wider',
                                    'text-emerald-400' => $invoice->isPaid(),
                                    'text-rose-400' => $invoice->isOverdue(),
                                    'text-slate-400' => ! $invoice->isPaid() && ! $invoice->isOverdue(),
                                ])>
                                    {{ $invoice->status }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-xs text-slate-500">
                            <p>No invoices recorded for this workspace.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- TAB 4: LIFECYCLE HISTORY --}}
        <div x-show="activeTab === 'lifecycle'" class="space-y-4">
            <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] shadow-sm overflow-hidden">
                <div class="border-b border-white/[0.08] px-5 py-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Delivered Lifecycle Communications</h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Audit log of system alerts, quota warnings, and billing notices sent to customer contacts.</p>
                </div>
                <div class="divide-y divide-white/[0.04]">
                    @forelse($lifecycleMessages as $message)
                        <div class="flex items-center justify-between p-4 text-xs">
                            <div class="min-w-0">
                                <p class="font-mono font-bold text-amber-300">{{ $message->key }}</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">{{ $message->recipients }} recipient(s)</p>
                            </div>
                            <span class="text-xs text-slate-400 tabular-nums font-medium">
                                {{ $message->sent_at->diffForHumans() }}
                            </span>
                        </div>
                    @empty
                        <div class="py-12 text-center text-xs text-slate-500">
                            <p>No lifecycle emails dispatched yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- TAB 5: OPERATIONAL CONTROLS --}}
        @if(auth('admin')->user()?->canManage())
            <div x-show="activeTab === 'controls'" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- Plan Switch Form --}}
                    <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-6 shadow-sm">
                        <h2 class="text-sm font-bold text-white mb-1">Administrative Plan Assignment</h2>
                        <p class="text-xs text-slate-400 mb-4">Directly update workspace plan tier without triggering credit card checkout.</p>
                        <form method="POST" action="{{ route('admin.tenants.plan', $tenant->id) }}" class="space-y-3">
                            @csrf
                            @method('PUT')
                            <div>
                                <label for="plan_id" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">Select Plan</label>
                                <select id="plan_id" name="plan_id" required class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-xs text-white ring-1 ring-white/10 focus:ring-2 focus:ring-amber-500">
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->id }}" @selected($subscription?->plan_id === $plan->id) class="bg-[#0c0c16]">
                                            {{ $plan->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="plan_reason" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">Audit Reason</label>
                                <input id="plan_reason" type="text" name="reason" placeholder="e.g. Enterprise pilot agreement" maxlength="255"
                                       class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-xs text-white ring-1 ring-white/10 placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500">
                            </div>
                            <button type="submit" class="w-full rounded-xl bg-amber-500 px-4 py-2.5 text-xs font-bold text-slate-950 transition hover:bg-amber-400 shadow-sm">
                                Apply Plan Override
                            </button>
                        </form>
                    </div>

                    {{-- Workspace Access Control --}}
                    <div class="rounded-2xl border border-white/[0.08] bg-[#0c0c16] p-6 shadow-sm">
                        <h2 class="text-sm font-bold text-white mb-1">Workspace Access Lock</h2>
                        <p class="text-xs text-slate-400 mb-4">Suspending pauses campaign sending and blocks sign-in. All contact data is preserved.</p>
                        <form id="access-status-form" method="POST" action="{{ route('admin.tenants.status', $tenant->id) }}" class="space-y-3">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="status" value="{{ $tenant->status === 'active' ? 'suspended' : 'active' }}">
                            <div>
                                <label for="status_reason" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">Audit Reason</label>
                                <input id="status_reason" type="text" name="reason" placeholder="e.g. Chargeback received / Terms violation" maxlength="255"
                                       class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-xs text-white ring-1 ring-white/10 placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500">
                            </div>
                            <button type="button"
                                    @click="window.confirmAction({
                                        title: '{{ $tenant->status === 'active' ? 'Suspend Workspace' : 'Reinstate Workspace' }}',
                                        message: '{{ $tenant->status === 'active' ? 'Suspending pauses sending across all relays. Data is safe.' : 'Reinstating restores sign-in and campaign sending immediately.' }}',
                                        formId: 'access-status-form',
                                        tone: '{{ $tenant->status === 'active' ? 'danger' : 'warning' }}'
                                    })"
                                    class="w-full rounded-xl px-4 py-2.5 text-xs font-bold transition shadow-sm {{ $tenant->status === 'active' ? 'bg-rose-500/20 text-rose-300 hover:bg-rose-500/30 border border-rose-500/30' : 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 border border-emerald-500/30' }}">
                                {{ $tenant->status === 'active' ? 'Suspend Workspace Access' : 'Reinstate Workspace Access' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>

</x-layouts.admin>
