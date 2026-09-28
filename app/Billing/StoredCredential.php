<?php

namespace App\Billing;

use App\Models\Subscription;

/**
 * The reusable half of a completed payment.
 *
 * Paystack hands back an authorization code, Stripe a customer plus a payment
 * method. Both are bearer credentials against the customer's card, so they are
 * stored encrypted and passed around in this narrow shape rather than as loose
 * strings that could end up in a log line.
 */
final readonly class StoredCredential
{
    public function __construct(
        public string $token,
        public ?string $customer = null,
        public ?string $brand = null,
        public ?string $lastFour = null,
    ) {}

    public static function fromSubscription(Subscription $subscription): ?self
    {
        if (blank($subscription->gateway_token)) {
            return null;
        }

        return new self(
            token: (string) $subscription->gateway_token,
            customer: $subscription->gateway_customer,
            brand: $subscription->card_brand,
            lastFour: $subscription->card_last_four,
        );
    }

    /** Safe to show a customer: "Visa ending 4242". */
    public function label(): string
    {
        if ($this->brand === null || $this->lastFour === null) {
            return 'Saved payment method';
        }

        return ucfirst($this->brand).' ending '.$this->lastFour;
    }
}
