<x-layouts.app :header="'Invoice '.$invoice->number">
    <div class="mx-auto max-w-3xl space-y-6">

        <div class="card p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-lg font-bold text-slate-900 dark:text-white">{{ $invoice->number }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Issued {{ $invoice->created_at->toFormattedDayDateString() }}
                        @if($invoice->due_at) · due {{ $invoice->due_at->toFormattedDayDateString() }}@endif
                    </p>
                </div>
                <div class="text-right">
                    <x-money :amount="$invoice->total" :currency="$invoice->currency" class="text-2xl font-black text-slate-900 dark:text-white" />
                    @if($invoice->amount_paid > 0 && ! $invoice->isPaid())
                        <p class="text-xs text-slate-500">
                            <x-money :amount="$invoice->amount_paid" :currency="$invoice->currency" /> paid ·
                            <x-money :amount="$invoice->balance()" :currency="$invoice->currency" /> outstanding
                        </p>
                    @endif
                    <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold
                        {{ $invoice->isPaid() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400' }}">
                        {{ $invoice->status }}
                    </span>
                </div>
            </div>

            <table class="mt-6 w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-400 dark:border-white/10">
                        <th class="pb-2 font-semibold">Description</th>
                        <th class="pb-2 text-right font-semibold">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/[0.04]">
                    @foreach($invoice->line_items ?? [] as $line)
                        <tr>
                            <td class="py-3 text-slate-700 dark:text-slate-300">{{ $line['description'] ?? '—' }}</td>
                            <td class="py-3 text-right"><x-money :amount="$line['amount'] ?? 0" :currency="$invoice->currency" /></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-200 dark:border-white/10">
                        <td class="pt-3 font-bold text-slate-900 dark:text-white">Total</td>
                        <td class="pt-3 text-right font-bold text-slate-900 dark:text-white">
                            <x-money :amount="$invoice->total" :currency="$invoice->currency" />
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Bank details shown after choosing the manual method --}}
        @if(session('bank_instructions'))
            <div class="card border-2 border-indigo-200 p-6 dark:border-indigo-500/30">
                <h3 class="mb-1 text-base font-bold text-slate-900 dark:text-white">Transfer these details</h3>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                    Include the payment reference or we cannot match your transfer.
                </p>
                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach(session('bank_instructions') as $label => $value)
                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/[0.03]">
                            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="mt-0.5 font-mono text-sm font-semibold text-slate-900 select-all dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <form method="POST" action="{{ route('billing.proof', $invoice->id) }}" enctype="multipart/form-data" class="mt-5 flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="hidden" name="reference" value="{{ session('bank_reference') }}">
                    <div class="flex-1">
                        <label for="proof" class="input-label">Upload proof of payment</label>
                        <input id="proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" required class="input">
                    </div>
                    <x-button type="submit" variant="primary">Submit</x-button>
                </form>
            </div>
        @endif

        {{-- Payment methods --}}
        @if($invoice->isPayable())
            <div class="card p-6">
                <h3 class="mb-4 text-base font-bold text-slate-900 dark:text-white">Pay this invoice</h3>

                @forelse($gateways as $key => $gateway)
                    <form method="POST" action="{{ route('billing.checkout.start', $invoice->id) }}" class="mb-3">
                        @csrf
                        <input type="hidden" name="gateway" value="{{ $key }}">
                        <button type="submit"
                                class="flex w-full items-center justify-between rounded-xl border border-slate-200 p-4 text-left transition hover:border-indigo-400 hover:bg-indigo-50/50 dark:border-white/10 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/5">
                            <span>
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $gateway->label() }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">
                                    @if($key === 'manual')
                                        Confirmed by our team once the funds arrive — usually within one business day.
                                    @else
                                        You'll be redirected to complete payment securely.
                                    @endif
                                </span>
                            </span>
                            <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </form>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        No payment method is configured for {{ $invoice->currency }}. Please contact support.
                    </p>
                @endforelse
            </div>
        @endif

        {{-- Ledger --}}
        @if($invoice->payments->isNotEmpty())
            <div class="card overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-white/[0.06]">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Payment history</h3>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-white/[0.04]">
                    @foreach($invoice->payments as $payment)
                        <div class="flex items-center justify-between px-6 py-3 text-sm">
                            <div>
                                <p class="font-medium text-slate-800 dark:text-slate-200">{{ ucfirst($payment->gateway) }}</p>
                                <p class="text-xs text-slate-400">
                                    {{ $payment->paid_at?->toDayDateTimeString() ?? 'Awaiting confirmation' }}
                                    @if($payment->failure_reason) · {{ $payment->failure_reason }}@endif
                                </p>
                            </div>
                            <div class="text-right">
                                <x-money :amount="$payment->amount" :currency="$payment->currency"
                                         class="font-semibold {{ $payment->amount < 0 ? 'text-rose-500' : 'text-slate-900 dark:text-white' }}" />
                                <p class="text-xs text-slate-400">{{ $payment->status }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <a href="{{ route('billing.index') }}" class="inline-block text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-400">← Back to billing</a>
    </div>
</x-layouts.app>
