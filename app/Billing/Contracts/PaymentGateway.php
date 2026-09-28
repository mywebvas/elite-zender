<?php

namespace App\Billing\Contracts;

use App\Billing\CheckoutSession;
use App\Billing\PaymentResult;
use App\Billing\StoredCredential;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * One interface, three very different payment rails.
 *
 * Every provider-specific quirk stops here: callers deal in invoices,
 * CheckoutSessions and PaymentResults, never in Paystack's kobo or Stripe's
 * PaymentIntents. Adding a fourth provider means adding a class, not touching
 * the controllers.
 */
interface PaymentGateway
{
    /** Stable machine key, stored on every payment row. */
    public function key(): string;

    /** Name shown to the customer at checkout. */
    public function label(): string;

    /** Has an operator supplied credentials for this gateway? */
    public function isConfigured(): bool;

    /** Can this gateway settle the given currency? */
    public function supports(string $currency): bool;

    /** Begin a payment for an invoice. */
    public function checkout(Invoice $invoice, string $callbackUrl): CheckoutSession;

    /** Confirm a payment with the provider — never trust the browser's word for it. */
    public function verify(string $reference): PaymentResult;

    /** Is this webhook genuinely from the provider? */
    public function verifyWebhook(Request $request): bool;

    /** Translate a verified webhook into a normalised result, or null if irrelevant. */
    public function parseWebhook(Request $request): ?PaymentResult;

    /**
     * Can this rail charge again later without the customer present?
     *
     * Offline bank transfer cannot, which is why renewal has to fall back to
     * issuing an invoice and asking.
     */
    public function supportsRecurring(): bool;

    /**
     * Charge a stored credential off-session.
     *
     * Only ever called with a token this gateway itself returned.
     */
    public function chargeStored(Invoice $invoice, StoredCredential $credential): PaymentResult;
}
