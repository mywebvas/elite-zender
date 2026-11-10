<x-layouts.admin title="Billing & Invoices" subtitle="Review incoming bank transfers, inspect issued invoices, and track revenue collection across all workspaces.">
    {{-- Header Action & Stats Bar --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold tracking-tight text-white">Billing & Invoices Ledger</h2>
                    <span class="inline-flex items-center rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-0.5 text-xs font-mono font-medium text-slate-300">
                        {{ number_format($invoices->total()) }} {{ Str::plural('invoice', $invoices->total()) }}
                    </span>
                </div>
                <p class="text-xs text-slate-400">Manage manual payment proofs, audit invoices, and void non-collectible balances.</p>
            </div>
        </div>
    </div>

    {{-- Pending Bank Transfers Queue (Action Required) --}}
    @if($pendingProofs->isNotEmpty())
        <div class="mb-8 rounded-2xl border border-amber-500/30 bg-gradient-to-b from-amber-500/[0.08] to-transparent p-5 shadow-2xl backdrop-blur-xl">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-amber-500/20 pb-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-2.5 w-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                    <h2 class="text-base font-semibold text-amber-300">Bank transfers awaiting confirmation</h2>
                    <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-xs font-mono font-bold text-amber-300 border border-amber-500/30">
                        {{ $pendingProofs->count() }} pending
                    </span>
                </div>
                <p class="text-xs text-slate-400">Manual wire receipts submitted by customers that require human operator verification.</p>
            </div>

            <div class="grid grid-cols-1 gap-4">
                @foreach($pendingProofs as $payment)
                    <div class="rounded-xl border border-white/[0.08] bg-[#0c0c16] p-4.5 transition hover:border-amber-500/30">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            {{-- Payment details --}}
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-base font-bold text-white">
                                        <x-money :amount="$payment->amount" :currency="$payment->currency" />
                                    </span>
                                    <span class="text-slate-400">for</span>
                                    @if($payment->tenant)
                                        <a href="{{ route('admin.tenants.show', $payment->tenant->id) }}"
                                           class="font-medium text-amber-400 hover:text-amber-300 hover:underline">
                                            {{ $payment->tenant->name }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">Unknown Workspace</span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400">
                                    <span class="font-mono text-slate-300">Invoice: {{ $payment->invoice?->number ?? '—' }}</span>
                                    <span class="text-slate-600">·</span>
                                    <span class="font-mono">Ref: <strong class="text-slate-200 select-all">{{ $payment->reference }}</strong></span>
                                    <span class="text-slate-600">·</span>
                                    <span>Submitted {{ $payment->created_at?->diffForHumans() ?? 'recently' }}</span>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex flex-wrap items-center gap-3">
                                {{-- Proof Receipt Link --}}
                                @if($payment->proof_path)
                                    <a href="{{ route('admin.payments.proof', $payment->id) }}" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1.5 rounded-xl border border-white/10 bg-white/[0.04] px-3.5 py-2 text-xs font-semibold text-slate-200 transition hover:bg-white/[0.08] hover:text-white">
                                        <svg class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>View proof</span>
                                    </a>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-xl border border-white/5 bg-white/[0.02] px-3 py-1.5 text-xs text-slate-500">
                                        No proof uploaded
                                    </span>
                                @endif

                                @if(auth('admin')->user()?->canManage())
                                    {{-- Confirm form with optional audit note --}}
                                    <form method="POST" action="{{ route('admin.payments.confirm', $payment->id) }}"
                                          x-data="{ confirming: false }"
                                          @submit="if(confirming){ $event.preventDefault(); return false; } if(!confirm('Confirm receipt of payment and settle invoice {{ $payment->invoice?->number }}?')){ return false; } confirming = true">
                                        @csrf
                                        <button type="submit" :disabled="confirming"
                                                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500 px-4 py-2 text-xs font-semibold text-slate-950 shadow-sm transition hover:bg-emerald-400 active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed">
                                            <svg class="h-4 w-4 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span x-text="confirming ? 'Confirming…' : 'Confirm'">Confirm</span>
                                        </button>
                                    </form>

                                    {{-- Reject form --}}
                                    <form method="POST" action="{{ route('admin.payments.reject', $payment->id) }}"
                                          x-data="{ rejecting: false }"
                                          @submit="if(rejecting){ $event.preventDefault(); return false; } if(!confirm('Reject this bank transfer receipt?')){ return false; } rejecting = true">
                                        @csrf
                                        <button type="submit" :disabled="rejecting"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-3.5 py-2 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/20 active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed">
                                            <svg class="h-4 w-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <span x-text="rejecting ? 'Rejecting…' : 'Reject'">Reject</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Filter & Search Toolbar --}}
    <div class="mb-6 rounded-2xl border border-white/[0.08] bg-[#0a0a12]/80 p-4 backdrop-blur-xl shadow-lg">
        <form method="GET" action="{{ route('admin.invoices.index') }}" class="flex flex-col gap-3 md:flex-row md:items-center">
            {{-- Search by invoice number --}}
            <div class="relative flex-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by invoice number (e.g. EZ-202610-00042)…"
                       @input.debounce.400ms="$el.form.requestSubmit()"
                       class="w-full rounded-xl border-0 bg-white/[0.04] py-2.5 pl-10 pr-4 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-500 transition focus:bg-white/[0.07] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
            </div>

            {{-- Status dropdown --}}
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative min-w-[140px]">
                    <select name="status"
                            @change="$el.form.requestSubmit()"
                            class="w-full appearance-none rounded-xl border-0 bg-white/[0.04] py-2.5 pl-3.5 pr-8 text-sm text-white ring-1 ring-white/10 transition focus:bg-[#0e0e18] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                        <option value="" class="bg-[#0e0e18] text-white">Any status</option>
                        @foreach(['open', 'paid', 'void', 'uncollectible'] as $status)
                            <option value="{{ $status }}" class="bg-[#0e0e18] text-white" @selected(request('status') === $status)>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>

                {{-- Submit Button --}}
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-[0.98]">
                    <span>Filter</span>
                </button>

                @if(request('search') || request('status'))
                    <a href="{{ route('admin.invoices.index') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-white/[0.08] hover:text-white">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Content Table / Mobile Cards Container --}}
    <div class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 shadow-xl overflow-hidden backdrop-blur-sm">
        @if($invoices->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.02] text-slate-500">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-semibold text-white">No invoices found</h3>
                <p class="mt-1 text-sm text-slate-400">
                    @if(request('search') || request('status'))
                        No invoice records match your current filter parameters. Try clearing your filters.
                    @else
                        No invoices have been billed or generated yet across any workspace.
                    @endif
                </p>
                @if(request('search') || request('status'))
                    <div class="mt-5">
                        <a href="{{ route('admin.invoices.index') }}"
                           class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2 text-xs font-semibold text-white transition hover:bg-white/[0.08]">
                            Clear filters
                        </a>
                    </div>
                @endif
            </div>
        @else
            {{-- Desktop Data Table (hidden on mobile, visible on lg) --}}
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/[0.08] bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-4">Invoice</th>
                            <th class="px-6 py-4">Workspace</th>
                            <th class="px-6 py-4">Plan</th>
                            <th class="px-6 py-4">Total</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Issued</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        @foreach($invoices as $invoice)
                            <tr class="group transition hover:bg-white/[0.02]">
                                {{-- Invoice Number --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-mono text-sm font-semibold text-white group-hover:text-amber-300 transition-colors">
                                        {{ $invoice->number }}
                                    </span>
                                </td>

                                {{-- Workspace Link --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($invoice->tenant)
                                        <a href="{{ route('admin.tenants.show', $invoice->tenant->id) }}"
                                           class="font-medium text-amber-400 hover:text-amber-300 hover:underline">
                                            {{ $invoice->tenant->name }}
                                        </a>
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>

                                {{-- Plan Name --}}
                                <td class="px-6 py-4 whitespace-nowrap text-slate-300">
                                    {{ $invoice->plan?->name ?? '—' }}
                                </td>

                                {{-- Total Amount with Monospace Money --}}
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-medium text-white">
                                    <x-money :amount="$invoice->total" :currency="$invoice->currency" />
                                </td>

                                {{-- Status Badge --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $badgeStyles = match(true) {
                                            $invoice->isPaid() => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                                            $invoice->isOverdue() => 'border-rose-500/30 bg-rose-500/10 text-rose-300',
                                            $invoice->status === 'void' => 'border-slate-500/30 bg-slate-500/10 text-slate-400',
                                            default => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $badgeStyles }}">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>

                                {{-- Created At --}}
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400 font-mono">
                                    {{ $invoice->created_at?->format('M j, Y') ?? '—' }}
                                </td>

                                {{-- Void Action Button --}}
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    @if(! $invoice->isPaid() && $invoice->status !== 'void' && auth('admin')->user()?->canManage())
                                        <form method="POST" action="{{ route('admin.invoices.void', $invoice->id) }}"
                                              x-data="{ voiding: false }"
                                              @submit="if(voiding){ $event.preventDefault(); return false; } if(!confirm('Void invoice {{ $invoice->number }} permanently?')){ return false; } voiding = true;">
                                            @csrf
                                            <button type="submit" :disabled="voiding"
                                                    :class="{ 'opacity-60 cursor-not-allowed': voiding }"
                                                    class="rounded-lg border border-white/5 px-2.5 py-1 text-xs font-medium text-slate-400 transition hover:border-rose-500/30 hover:bg-rose-500/10 hover:text-rose-300">
                                                <span x-text="voiding ? 'Voiding…' : 'Void'">Void</span>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards View (visible on mobile, hidden on lg) --}}
            <div class="lg:hidden divide-y divide-white/[0.04]">
                @foreach($invoices as $invoice)
                    @php
                        $badgeStyles = match(true) {
                            $invoice->isPaid() => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                            $invoice->isOverdue() => 'border-rose-500/30 bg-rose-500/10 text-rose-300',
                            $invoice->status === 'void' => 'border-slate-500/30 bg-slate-500/10 text-slate-400',
                            default => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
                        };
                    @endphp
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="font-mono text-sm font-semibold text-white">{{ $invoice->number }}</span>
                                <div class="mt-0.5 text-xs text-slate-400">
                                    @if($invoice->tenant)
                                        <a href="{{ route('admin.tenants.show', $invoice->tenant->id) }}" class="text-amber-400 hover:underline">
                                            {{ $invoice->tenant->name }}
                                        </a>
                                    @else
                                        <span>Unknown Workspace</span>
                                    @endif
                                    · {{ $invoice->plan?->name ?? 'Custom' }}
                                </div>
                            </div>
                            <span class="inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $badgeStyles }}">
                                {{ ucfirst($invoice->status) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between rounded-xl bg-white/[0.02] p-2.5 text-xs border border-white/5">
                            <span class="font-mono font-bold text-white text-sm">
                                <x-money :amount="$invoice->total" :currency="$invoice->currency" />
                            </span>
                            <span class="text-slate-400 font-mono">
                                {{ $invoice->created_at?->format('M j, Y') ?? '—' }}
                            </span>
                        </div>

                        @if(! $invoice->isPaid() && $invoice->status !== 'void' && auth('admin')->user()?->canManage())
                            <div class="pt-1">
                                <form method="POST" action="{{ route('admin.invoices.void', $invoice->id) }}"
                                      x-data="{ voiding: false }"
                                      @submit="if(voiding){ $event.preventDefault(); return false; } if(!confirm('Void invoice {{ $invoice->number }} permanently?')){ return false; } voiding = true;">
                                    @csrf
                                    <button type="submit" :disabled="voiding"
                                            :class="{ 'opacity-60 cursor-not-allowed': voiding }"
                                            class="w-full rounded-lg border border-white/10 bg-white/[0.02] py-2 text-xs font-medium text-slate-400 hover:border-rose-500/30 hover:bg-rose-500/10 hover:text-rose-300 transition">
                                        <span x-text="voiding ? 'Voiding Invoice…' : 'Void Invoice'">Void Invoice</span>
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Pagination Bar --}}
    @if($invoices->hasPages())
        <div class="mt-6">
            {{ $invoices->links() }}
        </div>
    @endif
</x-layouts.admin>
