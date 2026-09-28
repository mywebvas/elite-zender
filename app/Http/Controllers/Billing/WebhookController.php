<?php

namespace App\Http\Controllers\Billing;

use App\Billing\BillingService;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gateway webhooks.
 *
 * Unauthenticated by necessity and CSRF-exempt, so the signature check is the
 * entire security boundary. Nothing in this class reads the request body until
 * `verifyWebhook()` has passed.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        if (! $this->billing->gateways()->has($gateway)) {
            return response()->json(['message' => 'Unknown gateway.'], 404);
        }

        $driver = $this->billing->gateways()->get($gateway);

        if (! $driver->verifyWebhook($request)) {
            Log::warning('Rejected webhook with an invalid signature', [
                'gateway' => $gateway,
                'ip' => $request->ip(),
            ]);

            // 401, not 400: a forged signature is an authentication failure,
            // and providers do not retry a 401 forever.
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $result = $driver->parseWebhook($request);

        if ($result === null || ! $result->successful) {
            // Acknowledged so the provider stops retrying an event we simply
            // do not act on.
            return response()->json(['message' => 'Ignored.']);
        }

        $invoice = $this->resolveInvoice($result->reference, $result->raw);

        if ($invoice === null) {
            Log::warning('Webhook referenced an unknown invoice', [
                'gateway' => $gateway,
                'reference' => $result->reference,
            ]);

            return response()->json(['message' => 'Unknown invoice.']);
        }

        // recordPayment() is idempotent on (gateway, gateway_ref), which the
        // database enforces — a replayed webhook credits nothing twice.
        $this->billing->recordPayment($invoice, $result);

        return response()->json(['message' => 'Recorded.']);
    }

    /** @param array<string, mixed> $raw */
    private function resolveInvoice(?string $reference, array $raw): ?Invoice
    {
        if (filled($reference)) {
            $payment = Payment::withoutGlobalScopes()->firstWhere('reference', $reference);

            if ($payment?->invoice_id !== null) {
                return Invoice::withoutGlobalScopes()->find($payment->invoice_id);
            }
        }

        $invoiceId = data_get($raw, 'metadata.invoice_id');

        return is_string($invoiceId) ? Invoice::withoutGlobalScopes()->find($invoiceId) : null;
    }
}
