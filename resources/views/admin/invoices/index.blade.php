<x-layouts.admin title="Billing">

    @if($pendingProofs->isNotEmpty())
        <div class="mb-6 rounded-xl border border-amber-500/30 bg-amber-500/5">
            <div class="border-b border-amber-500/20 px-5 py-3">
                <h2 class="text-sm font-semibold text-amber-300">Bank transfers awaiting confirmation</h2>
            </div>
            @foreach($pendingProofs as $payment)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 px-5 py-4 last:border-0">
                    <div class="min-w-0">
                        <p class="text-sm font-medium">{{ $payment->tenant?->name ?? 'Unknown' }}</p>
                        <p class="font-mono text-xs text-slate-400">
                            {{ $payment->invoice?->number }} · ref {{ $payment->reference }} ·
                            <x-money :amount="$payment->amount" :currency="$payment->currency" />
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if($payment->proof_path)
                            <a href="{{ route('admin.payments.proof', $payment->id) }}" target="_blank" rel="noopener"
                               class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold hover:bg-white/20">View proof</a>
                        @else
                            <span class="text-xs text-slate-500">No proof uploaded</span>
                        @endif

                        @if(auth('admin')->user()?->canManage())
                            <form method="POST" action="{{ route('admin.payments.confirm', $payment->id) }}"
                                  onsubmit="return confirm('Confirm this transfer and settle the invoice?')">
                                @csrf
                                <button type="submit" class="rounded-lg bg-emerald-500/20 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/30">
                                    Confirm
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.payments.reject', $payment->id) }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-rose-500/20 px-3 py-1.5 text-xs font-semibold text-rose-300 hover:bg-rose-500/30">
                                    Reject
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-3">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Invoice number…"
               class="flex-1 rounded-lg border-0 bg-white/5 px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-500">
        <select name="status" class="rounded-lg border-0 bg-white/5 px-3 py-2 text-sm text-white ring-1 ring-white/10">
            <option value="">Any status</option>
            @foreach(['open', 'paid', 'void', 'uncollectible'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-white/10 bg-[#0b0b14]">
        <table class="w-full text-sm">
            <thead class="border-b border-white/10 text-left text-xs uppercase tracking-wider text-slate-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Invoice</th>
                    <th class="px-5 py-3 font-semibold">Workspace</th>
                    <th class="px-5 py-3 font-semibold">Plan</th>
                    <th class="px-5 py-3 font-semibold">Total</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($invoices as $invoice)
                    <tr class="hover:bg-white/5">
                        <td class="px-5 py-3 font-mono">{{ $invoice->number }}</td>
                        <td class="px-5 py-3">{{ $invoice->tenant?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-slate-400">{{ $invoice->plan?->name ?? '—' }}</td>
                        <td class="px-5 py-3"><x-money :amount="$invoice->total" :currency="$invoice->currency" /></td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs
                                {{ $invoice->isPaid() ? 'bg-emerald-500/15 text-emerald-400' : ($invoice->isOverdue() ? 'bg-rose-500/15 text-rose-400' : 'bg-white/10 text-slate-300') }}">
                                {{ $invoice->status }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if(! $invoice->isPaid() && $invoice->status !== 'void' && auth('admin')->user()?->canManage())
                                <form method="POST" action="{{ route('admin.invoices.void', $invoice->id) }}"
                                      onsubmit="return confirm('Void {{ $invoice->number }}?')">
                                    @csrf
                                    <button type="submit" class="text-xs text-slate-400 hover:text-rose-400">Void</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No invoices.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.admin>
