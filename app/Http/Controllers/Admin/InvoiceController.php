<?php

namespace App\Http\Controllers\Admin;

use App\Billing\BillingService;
use App\Billing\PaymentResult;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.invoices.index', [
            'invoices' => Invoice::withoutGlobalScopes()
                ->with(['tenant:id,name', 'plan:id,name'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->when($request->filled('search'), fn ($q) => $q->where('number', 'like', '%'.$request->string('search').'%'))
                ->latest()
                ->paginate(40)
                ->withQueryString(),
            'pendingProofs' => Payment::withoutGlobalScopes()
                ->with(['tenant:id,name', 'invoice:id,number,currency,total,amount_paid'])
                ->where('gateway', 'manual')
                ->where('status', Payment::STATUS_PENDING)
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Confirm an offline bank transfer.
     *
     * The synthetic gateway reference is derived from the payment row's own id,
     * so clicking "confirm" twice cannot credit the invoice twice — the unique
     * index on (gateway, gateway_ref) rejects the second write.
     */
    public function confirmTransfer(Request $request, string $paymentId): RedirectResponse
    {
        $payment = Payment::withoutGlobalScopes()->with('invoice')->findOrFail($paymentId);

        abort_unless($payment->isAwaitingReview(), 422, 'That payment is not awaiting review.');
        abort_if($payment->invoice === null, 422, 'That payment has no invoice.');

        $validated = $request->validate([
            'amount' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = $validated['amount'] ?? $payment->amount;

        $this->billing->recordPayment($payment->invoice, PaymentResult::success(
            gateway: 'manual',
            gatewayRef: 'manual_'.$payment->getKey(),
            reference: $payment->reference,
            amount: (int) $amount,
            currency: $payment->currency,
            raw: ['confirmed_by' => auth('admin')->id(), 'note' => $validated['note'] ?? null],
        ));

        $payment->forceFill([
            'status' => Payment::STATUS_SUCCEEDED,
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => now(),
            'paid_at' => now(),
        ])->save();

        Log::warning('Admin confirmed a bank transfer', [
            'admin_id' => auth('admin')->id(),
            'payment_id' => $payment->getKey(),
            'invoice' => $payment->invoice->number,
            'amount' => $amount,
        ]);

        return back()->with('success', 'Transfer confirmed and invoice settled.');
    }

    public function rejectTransfer(Request $request, string $paymentId): RedirectResponse
    {
        $payment = Payment::withoutGlobalScopes()->findOrFail($paymentId);

        abort_unless($payment->isAwaitingReview(), 422);

        $payment->forceFill([
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => $request->string('reason')->toString() ?: 'Could not verify the transfer.',
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => now(),
        ])->save();

        return back()->with('success', 'Transfer rejected.');
    }

    /** Write off an invoice that will never be collected. */
    public function void(Request $request, string $id): RedirectResponse
    {
        $invoice = Invoice::withoutGlobalScopes()->findOrFail($id);

        abort_if($invoice->isPaid(), 422, 'A paid invoice cannot be voided — issue a refund instead.');

        $invoice->forceFill([
            'status' => Invoice::STATUS_VOID,
            'voided_at' => now(),
            'metadata' => array_merge($invoice->metadata ?? [], [
                'voided_by' => auth('admin')->id(),
                'void_reason' => $request->string('reason')->toString(),
            ]),
        ])->save();

        return back()->with('success', "Invoice {$invoice->number} voided.");
    }

    /**
     * Record a refund as a new negative ledger entry.
     *
     * Never edits the original payment: an append-only ledger is the only kind
     * a finance team can reconcile.
     */
    public function refund(Request $request, string $paymentId): RedirectResponse
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = auth('admin')->user();

        abort_unless($admin?->isSuperAdmin() ?? false, 403);

        $payment = Payment::withoutGlobalScopes()->findOrFail($paymentId);

        abort_unless($payment->status === Payment::STATUS_SUCCEEDED, 422);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.$payment->amount],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        Payment::withoutGlobalScopes()->create([
            'tenant_id' => $payment->tenant_id,
            'invoice_id' => $payment->invoice_id,
            'gateway' => $payment->gateway,
            'reference' => $payment->reference,
            'gateway_ref' => 'refund_'.Str::uuid7(),
            'status' => Payment::STATUS_SUCCEEDED,
            'currency' => $payment->currency,
            'amount' => -1 * (int) $validated['amount'],
            'failure_reason' => $validated['reason'],
            'paid_at' => now(),
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => now(),
        ]);

        $payment->forceFill(['status' => Payment::STATUS_REFUNDED])->save();

        if ($payment->invoice !== null) {
            $this->billing->settle($payment->invoice);
        }

        Log::warning('Admin issued a refund', [
            'admin_id' => auth('admin')->id(),
            'payment_id' => $payment->getKey(),
            'amount' => $validated['amount'],
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Refund recorded.');
    }

    /** Payment proofs are private customer documents — streamed, never public. */
    public function proof(string $paymentId): StreamedResponse
    {
        $payment = Payment::withoutGlobalScopes()->findOrFail($paymentId);

        abort_if($payment->proof_path === null, 404);
        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        // Streamed, never made public: a payment proof is a customer's bank
        // document.
        return $disk->response($payment->proof_path);
    }
}
