<?php

namespace App\Billing;

/**
 * Where to send the customer to pay, and how.
 *
 * `redirectUrl` is a hosted checkout (Paystack, Stripe). `instructions` is for
 * offline methods where there is nowhere to redirect to — the customer is shown
 * bank details instead.
 */
final readonly class CheckoutSession
{
    /** @param array<string, string> $instructions */
    public function __construct(
        public string $gateway,
        public string $reference,
        public ?string $redirectUrl = null,
        public array $instructions = [],
    ) {}

    public function requiresRedirect(): bool
    {
        return $this->redirectUrl !== null;
    }
}
