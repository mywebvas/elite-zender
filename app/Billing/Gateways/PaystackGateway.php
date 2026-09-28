<?php

namespace App\Billing\Gateways;

use App\Billing\CheckoutSession;
use App\Billing\Contracts\PaymentGateway;
use App\Billing\PaymentResult;
use App\Billing\StoredCredential;
use App\Models\Invoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Paystack, over its REST API.
 *
 * Implemented directly against the HTTP endpoints rather than the official SDK:
 * the surface we need is three calls, and a hand-rolled client keeps the
 * dependency tree (and its transitive CVEs) out of a system that touches money.
 *
 * Paystack quotes amounts in the currency's minor unit, which matches how this
 * application stores money, so no conversion is needed.
 */
final class PaystackGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'paystack';
    }

    public function label(): string
    {
        return 'Card, bank transfer or USSD (Paystack)';
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

        $response = $this->client()->post('/transaction/initialize', [
            'email' => $this->billingEmail($invoice),
            'amount' => $invoice->balance(),
            'currency' => strtoupper($invoice->currency),
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => [
                'invoice_id' => $invoice->getKey(),
                'invoice_number' => $invoice->number,
                'tenant_id' => $invoice->tenant_id,
            ],
        ]);

        $body = $response->json();

        if (! $response->successful() || ($body['status'] ?? false) !== true) {
            throw new RuntimeException(
                'Paystack could not start this payment: '.($body['message'] ?? 'unknown error'),
            );
        }

        return new CheckoutSession(
            gateway: $this->key(),
            reference: $reference,
            redirectUrl: $body['data']['authorization_url'],
        );
    }

    /**
     * Confirm with Paystack directly.
     *
     * The browser's redirect back is only a hint — anyone can craft that URL.
     * Money is only ever recorded on the strength of this server-to-server call
     * or a signature-verified webhook.
     */
    public function verify(string $reference): PaymentResult
    {
        try {
            $response = $this->client()->get('/transaction/verify/'.urlencode($reference));
        } catch (ConnectionException $e) {
            return PaymentResult::failure($this->key(), 'Could not reach Paystack: '.$e->getMessage(), $reference);
        }

        $body = $response->json();
        $data = $body['data'] ?? [];

        if (! $response->successful() || ($body['status'] ?? false) !== true) {
            return PaymentResult::failure($this->key(), $body['message'] ?? 'Verification failed', $reference, (array) $body);
        }

        if (($data['status'] ?? null) !== 'success') {
            return PaymentResult::failure(
                $this->key(),
                $data['gateway_response'] ?? 'Payment was not completed',
                $reference,
                (array) $data,
            );
        }

        return PaymentResult::success(
            gateway: $this->key(),
            gatewayRef: (string) ($data['id'] ?? $reference),
            reference: $reference,
            amount: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? 'NGN')),
            raw: (array) $data,
        );
    }

    /**
     * Paystack signs the raw body with HMAC-SHA512 keyed on the secret key.
     *
     * `hash_equals` rather than `===`: a naive comparison leaks the correct
     * signature one byte at a time through timing.
     */
    public function verifyWebhook(Request $request): bool
    {
        $signature = $request->header('x-paystack-signature');

        if (! is_string($signature) || ! $this->isConfigured()) {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), (string) $this->config('secret_key'));

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): ?PaymentResult
    {
        $event = (string) $request->input('event');
        $data = (array) $request->input('data', []);

        if ($event !== 'charge.success') {
            // Everything else (transfers, subscriptions, disputes) is logged
            // for later handling but must not move money here.
            Log::info('Paystack webhook ignored', ['event' => $event]);

            return null;
        }

        return PaymentResult::success(
            gateway: $this->key(),
            gatewayRef: (string) ($data['id'] ?? ''),
            reference: (string) ($data['reference'] ?? ''),
            amount: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? 'NGN')),
            raw: $data,
        );
    }

    public function supportsRecurring(): bool
    {
        return true;
    }

    /**
     * Re-charge a saved authorization.
     *
     * Paystack returns `authorization.authorization_code` on the first
     * successful charge and it stays valid for that card; this is the endpoint
     * the customer never has to see again.
     */
    public function chargeStored(Invoice $invoice, StoredCredential $credential): PaymentResult
    {
        $reference = 'ezr_'.Str::lower(Str::random(24));

        try {
            $response = $this->client()->post('/transaction/charge_authorization', [
                'authorization_code' => $credential->token,
                'email' => $credential->customer ?? $this->billingEmail($invoice),
                'amount' => $invoice->balance(),
                'currency' => strtoupper($invoice->currency),
                'reference' => $reference,
                'metadata' => [
                    'invoice_id' => $invoice->getKey(),
                    'tenant_id' => $invoice->tenant_id,
                    'reason' => 'renewal',
                ],
            ]);
        } catch (ConnectionException $e) {
            return PaymentResult::failure($this->key(), 'Could not reach Paystack: '.$e->getMessage(), $reference);
        }

        $body = $response->json();
        $data = $body['data'] ?? [];

        if (! $response->successful() || ($data['status'] ?? null) !== 'success') {
            return PaymentResult::failure(
                $this->key(),
                $data['gateway_response'] ?? ($body['message'] ?? 'The card was declined.'),
                $reference,
                (array) $data,
            );
        }

        return PaymentResult::success(
            gateway: $this->key(),
            gatewayRef: (string) ($data['id'] ?? $reference),
            reference: $reference,
            amount: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? 'NGN')),
            raw: (array) $data,
        );
    }

    /**
     * Pull the reusable credential out of a completed charge, if the customer's
     * card can be charged again.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function credentialFrom(array $raw): ?StoredCredential
    {
        $authorization = $raw['authorization'] ?? null;

        if (! is_array($authorization) || ($authorization['reusable'] ?? false) !== true) {
            return null;
        }

        $code = $authorization['authorization_code'] ?? null;

        return is_string($code) && $code !== ''
            ? new StoredCredential(
                token: $code,
                customer: $raw['customer']['email'] ?? null,
                brand: $authorization['brand'] ?? null,
                lastFour: $authorization['last4'] ?? null,
            )
            : null;
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken((string) $this->config('secret_key'))
            ->baseUrl((string) $this->config('base_url'))
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 250, throw: false);
    }

    private function billingEmail(Invoice $invoice): string
    {
        return $invoice->tenant?->users()->oldest()->value('email')
            ?? (string) config('mail.from.address');
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config("billing.gateways.paystack.{$key}", $default);
    }
}
