<?php

namespace App\Http\Controllers\Billing;

use App\Billing\BillingService;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    /** Start a payment and hand the customer to the chosen rail. */
    public function start(Request $request, string $invoiceId): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRoleAtLeast(Role::ADMIN) ?? false, 403);

        $invoice = Invoice::findOrFail($invoiceId);

        abort_unless($invoice->isPayable(), 422, 'This invoice is not payable.');

        $validated = $request->validate([
            'gateway' => ['required', 'string', 'max:30'],
        ]);

        $available = $this->billing->gateways()->availableFor($invoice->currency);

        if (! isset($available[$validated['gateway']])) {
            return back()->withErrors('That payment method is not available for this currency.');
        }

        $gateway = $available[$validated['gateway']];

        try {
            $session = $gateway->checkout($invoice, route('billing.checkout.callback', $invoice->id));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors($e->getMessage());
        }

        // Record the attempt before redirecting: if the customer pays and the
        // callback never arrives, this row is what lets support reconcile.
        Payment::withoutGlobalScopes()->create([
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->getKey(),
            'gateway' => $gateway->key(),
            'reference' => $session->reference,
            'status' => Payment::STATUS_PENDING,
            'currency' => $invoice->currency,
            'amount' => $invoice->balance(),
        ]);

        if ($session->requiresRedirect()) {
            return redirect()->away($session->redirectUrl);
        }

        return redirect()
            ->route('billing.invoices.show', $invoice->id)
            ->with('bank_instructions', $session->instructions)
            ->with('bank_reference', $session->reference);
    }

    /**
     * Where the gateway returns the customer.
     *
     * The redirect itself proves nothing — anyone can open this URL — so the
     * payment is confirmed server-to-server before a single naira is credited.
     */
    public function callback(Request $request, string $invoiceId): RedirectResponse
    {
        $invoice = Invoice::findOrFail($invoiceId);

        $reference = (string) $request->query('reference', $request->query('trxref', ''));

        if ($reference === '') {
            return redirect()->route('billing.invoices.show', $invoice->id)
                ->withErrors('We could not identify that payment. If you were charged, contact support.');
        }

        $attempt = Payment::withoutGlobalScopes()
            ->where('invoice_id', $invoice->getKey())
            ->where('reference', $reference)
            ->first();

        if ($attempt === null) {
            return redirect()->route('billing.invoices.show', $invoice->id)
                ->withErrors('Unknown payment reference.');
        }

        $result = $this->billing->gateways()->get($attempt->gateway)->verify($reference);

        if (! $result->successful) {
            $attempt->forceFill([
                'status' => Payment::STATUS_FAILED,
                'failure_reason' => $result->failureReason,
            ])->save();

            return redirect()->route('billing.invoices.show', $invoice->id)
                ->withErrors($result->failureReason ?? 'That payment did not complete.');
        }

        // The pending row has served its purpose; the authoritative record is
        // written by recordPayment(), keyed on the gateway reference.
        $attempt->delete();

        $this->billing->recordPayment($invoice, $result);

        return redirect()->route('billing.index')->with('success', 'Payment received — thank you.');
    }

    /** Upload proof of an offline bank transfer. */
    public function uploadProof(Request $request, string $invoiceId): RedirectResponse
    {
        $invoice = Invoice::findOrFail($invoiceId);

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('proof')->store('payment-proofs/'.$invoice->tenant_id, 'local');

        Payment::withoutGlobalScopes()
            ->where('invoice_id', $invoice->getKey())
            ->where('reference', $validated['reference'])
            ->where('status', Payment::STATUS_PENDING)
            ->update(['proof_path' => $path, 'updated_at' => now()]);

        Log::info('Bank transfer proof uploaded', [
            'invoice' => $invoice->number,
            'tenant_id' => $invoice->tenant_id,
        ]);

        return back()->with('success', 'Proof uploaded. We will confirm your payment shortly.');
    }
}
