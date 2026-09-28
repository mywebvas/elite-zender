<?php

namespace App\Billing;

/**
 * Normalised outcome of a gateway interaction, so callers never branch on
 * provider-specific payload shapes.
 */
final readonly class PaymentResult
{
    /**
     * @param  int  $amount  minor units
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $successful,
        public string $gateway,
        public ?string $gatewayRef,
        public ?string $reference,
        public int $amount,
        public string $currency,
        public ?string $failureReason = null,
        public array $raw = [],
    ) {}

    /** @param array<string, mixed> $raw */
    public static function success(string $gateway, ?string $gatewayRef, ?string $reference, int $amount, string $currency, array $raw = []): self
    {
        return new self(true, $gateway, $gatewayRef, $reference, $amount, $currency, null, $raw);
    }

    /** @param array<string, mixed> $raw */
    public static function failure(string $gateway, string $reason, ?string $reference = null, array $raw = []): self
    {
        return new self(false, $gateway, null, $reference, 0, 'USD', $reason, $raw);
    }
}
