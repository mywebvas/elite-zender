<?php

namespace App\Http\Controllers\Billing;

use App\Billing\BillingService;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Services\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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

        if ($gateway->key() === 'manual') {
            $existingPending = Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->getKey())
                ->where('gateway', 'manual')
                ->where('status', Payment::STATUS_PENDING)
                ->first();

            if ($existingPending !== null) {
                return redirect()
                    ->route('billing.invoices.show', $invoice->id)
                    ->with('bank_instructions', $gateway->checkout($invoice, '')->instructions)
                    ->with('bank_reference', $existingPending->reference);
            }
        }

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

        // If the invoice is already paid, redirect safely without repeating gateway calls
        if ($invoice->isPaid()) {
            return redirect()->route('billing.index')->with('success', 'Payment received — thank you.');
        }

        $attempt = Payment::withoutGlobalScopes()
            ->where('invoice_id', $invoice->getKey())
            ->where(function ($q) use ($reference): void {
                $q->where('reference', $reference)
                    ->orWhere('gateway_ref', $reference);
            })
            ->first();

        if ($attempt !== null && $attempt->status === Payment::STATUS_SUCCEEDED) {
            return redirect()->route('billing.index')->with('success', 'Payment received — thank you.');
        }

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

        try {
            $this->billing->recordPayment($invoice, $result);
        } catch (RuntimeException $e) {
            // The ledger refused the payment (a currency that does not match
            // the invoice is the only case today). Never swallow it, and
            // never delete the pending attempt: it is the only trace left of
            // money the customer has actually parted with.
            report($e);

            return redirect()->route('billing.invoices.show', $invoice->id)->withErrors(
                'We could not apply that payment automatically. Our team has been alerted — '
                ."quote reference {$reference} if you need to chase it.",
            );
        }

        // The pending row has served its purpose; the authoritative record is
        // written by recordPayment(), keyed on the gateway reference. It is
        // removed only once that record exists.
        $attempt->delete();

        return redirect()->route('billing.index')->with('success', 'Payment received — thank you.');
    }

    /** Upload proof of an offline bank transfer. */
    public function uploadProof(Request $request, string $invoiceId, ImageOptimizer $optimizer): RedirectResponse
    {
        $invoice = Invoice::findOrFail($invoiceId);

        abort_if($invoice->isPaid(), 422, 'This invoice is already paid.');

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $proofFile */
        $proofFile = $request->file('proof');
        $path = $optimizer->storeAndOptimize($proofFile, 'payment-proofs/'.$invoice->tenant_id, 'local');

        $updated = Payment::withoutGlobalScopes()
            ->where('invoice_id', $invoice->getKey())
            ->where('reference', $validated['reference'])
            ->where('status', Payment::STATUS_PENDING)
            ->update(['proof_path' => $path, 'updated_at' => now()]);

        if ($updated === 0) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);

            return back()->withErrors('No pending payment matching that reference was found.');
        }

        Log::info('Bank transfer proof uploaded', [
            'invoice' => $invoice->number,
            'tenant_id' => $invoice->tenant_id,
            'path' => $path,
        ]);

        return back()->with('success', 'Proof uploaded. We will confirm your payment shortly.');
    }
}
