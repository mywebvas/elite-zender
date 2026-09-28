<?php

namespace App\Billing\Gateways;

use App\Billing\CheckoutSession;
use App\Billing\Contracts\PaymentGateway;
use App\Billing\PaymentResult;
use App\Billing\StoredCredential;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stripe Checkout, over the REST API.
 *
 * Shipped behind a configuration flag: with no STRIPE_SECRET_KEY set,
 * `isConfigured()` returns false and the gateway never appears at checkout, so
 * the code below cannot run against an unverified account. Set the three env
 * vars and it becomes selectable — no deploy or code change required.
 *
 * Verified against the documented v1 endpoints. The one thing that genuinely
 * needs a live account to confirm is webhook delivery, so treat the first
 * production event as a smoke test.
 */
final class StripeGateway implements PaymentGateway
{
    /** Stripe tolerates at most this much clock skew on a webhook. */
    private const WEBHOOK_TOLERANCE_SECONDS = 300;

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Card (Stripe)';
    }

    public function isConfigured(): bool
    {
        return filled($this->config('secret_key'));
    }

    public function supports(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->config('currencies', []), true);
    }

    public function checkout(Invoice $invoice, string $callbackUrl): CheckoutSession
    {
        $reference = 'ez_'.Str::lower(Str::random(24));

        // Stripe's form encoding is bracketed rather than JSON.
        $response = Http::withToken((string) $this->config('secret_key'))
            ->baseUrl((string) $this->config('base_url'))
            ->asForm()
            ->timeout(20)
            ->post('/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $callbackUrl.'?reference='.$reference,
                'cancel_url' => $callbackUrl.'?reference='.$reference.'&cancelled=1',
                'client_reference_id' => $reference,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower($invoice->currency),
                'line_items[0][price_data][unit_amount]' => $invoice->balance(),
                'line_items[0][price_data][product_data][name]' => 'Invoice '.$invoice->number,
                'metadata[invoice_id]' => $invoice->getKey(),
                'metadata[tenant_id]' => $invoice->tenant_id,
                // Ask Stripe to keep the card on file so the subscription can
                // renew without dragging the customer back through checkout.
                'payment_intent_data[setup_future_usage]' => 'off_session',
                'customer_creation' => 'always',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Stripe could not start this payment: '.($response->json('error.message') ?? 'unknown error'),
            );
        }

        return new CheckoutSession(
            gateway: $this->key(),
            reference: $reference,
            redirectUrl: (string) $response->json('url'),
        );
    }

    public function verify(string $reference): PaymentResult
    {
        $response = Http::withToken((string) $this->config('secret_key'))
            ->baseUrl((string) $this->config('base_url'))
            ->timeout(20)
            ->get('/v1/checkout/sessions', ['limit' => 1, 'client_reference_id' => $reference]);

        $session = $response->json('data.0');

        if (! is_array($session)) {
            return PaymentResult::failure($this->key(), 'No Stripe session found for this reference.', $reference);
        }

        if (($session['payment_status'] ?? null) !== 'paid') {
            return PaymentResult::failure($this->key(), 'Payment was not completed.', $reference, $session);
        }

        return PaymentResult::success(
            gateway: $this->key(),
            gatewayRef: (string) ($session['payment_intent'] ?? $session['id']),
            reference: $reference,
            amount: (int) ($session['amount_total'] ?? 0),
            currency: strtoupper((string) ($session['currency'] ?? 'usd')),
            raw: $session,
        );
    }

    /**
     * Stripe signs `{timestamp}.{body}` with HMAC-SHA256 and sends it in the
     * Stripe-Signature header. The timestamp check is what stops an attacker
     * replaying a genuine, correctly signed event months later.
     */
    public function verifyWebhook(Request $request): bool
    {
        $header = (string) $request->header('Stripe-Signature');
        $secret = (string) $this->config('webhook_secret');

        if ($header === '' || $secret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if ($timestamp <= 0 || abs(time() - $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($parts['v1'] ?? [] as $candidate) {
            if (is_string($candidate) && hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhook(Request $request): ?PaymentResult
    {
        if ((string) $request->input('type') !== 'checkout.session.completed') {
            return null;
        }

        $session = (array) $request->input('data.object', []);

        if (($session['payment_status'] ?? null) !== 'paid') {
            return null;
        }

        return PaymentResult::success(
            gateway: $this->key(),
            gatewayRef: (string) ($session['payment_intent'] ?? $session['id'] ?? ''),
            reference: (string) ($session['client_reference_id'] ?? ''),
            amount: (int) ($session['amount_total'] ?? 0),
            currency: strtoupper((string) ($session['currency'] ?? 'usd')),
            raw: $session,
        );
    }

    public function supportsRecurring(): bool
    {
        return true;
    }

    /**
     * Charge a saved payment method without the customer present.
     *
     * `off_session` + `confirm` is the documented pattern for merchant-
     * initiated renewals; a card that needs 3-D Secure will decline here with
     * `authentication_required`, which is exactly what dunning should surface
     * rather than silently swallow.
     */
    public function chargeStored(Invoice $invoice, StoredCredential $credential): PaymentResult
    {
        $reference = 'ezr_'.Str::lower(Str::random(24));

        $response = Http::withToken((string) $this->config('secret_key'))
            ->baseUrl((string) $this->config('base_url'))
            ->asForm()
            ->timeout(20)
            ->post('/v1/payment_intents', array_filter([
                'amount' => $invoice->balance(),
                'currency' => strtolower($invoice->currency),
                'customer' => $credential->customer,
                'payment_method' => $credential->token,
                'off_session' => 'true',
                'confirm' => 'true',
                'description' => 'Invoice '.$invoice->number,
                'metadata[invoice_id]' => $invoice->getKey(),
                'metadata[tenant_id]' => $invoice->tenant_id,
            ], static fn ($value) => $value !== null));

        $body = $response->json();

        if (! $response->successful() || ($body['status'] ?? null) !== 'succeeded') {
            return PaymentResult::failure(
                $this->key(),
                $body['error']['message'] ?? ($body['last_payment_error']['message'] ?? 'The card was declined.'),
                $reference,
                (array) $body,
            );
        }

        return PaymentResult::success(
            gateway: $this->key(),
            gatewayRef: (string) $body['id'],
            reference: $reference,
            amount: (int) ($body['amount_received'] ?? $body['amount'] ?? 0),
            currency: strtoupper((string) ($body['currency'] ?? 'usd')),
            raw: (array) $body,
        );
    }

    /**
     * Resolve the reusable credential for a completed Checkout Session.
     *
     * The session only carries ids, so the payment method has to be fetched to
     * learn the brand and last four — the two things a customer needs to
     * recognise their own card on the billing page.
     *
     * @param  array<string, mixed>  $session
     */
    public function credentialFromSession(array $session): ?StoredCredential
    {
        $customer = $session['customer'] ?? null;
        $intentId = $session['payment_intent'] ?? null;

        if (! is_string($intentId) || $intentId === '') {
            return null;
        }

        $intent = Http::withToken((string) $this->config('secret_key'))
            ->baseUrl((string) $this->config('base_url'))
            ->timeout(20)
            ->get('/v1/payment_intents/'.$intentId)
            ->json();

        $paymentMethod = $intent['payment_method'] ?? null;

        if (! is_string($paymentMethod) || $paymentMethod === '') {
            return null;
        }

        $method = Http::withToken((string) $this->config('secret_key'))
            ->baseUrl((string) $this->config('base_url'))
            ->timeout(20)
            ->get('/v1/payment_methods/'.$paymentMethod)
            ->json();

        return new StoredCredential(
            token: $paymentMethod,
            customer: is_string($customer) ? $customer : null,
            brand: $method['card']['brand'] ?? null,
            lastFour: $method['card']['last4'] ?? null,
        );
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config("billing.gateways.stripe.{$key}", $default);
    }
}
