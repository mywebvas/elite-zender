<?php

namespace App\Billing;

use App\Billing\Contracts\PaymentGateway;
use App\Billing\Gateways\ManualBankTransferGateway;
use App\Billing\Gateways\PaystackGateway;
use App\Billing\Gateways\StripeGateway;
use InvalidArgumentException;

/**
 * Registry of payment rails.
 *
 * Adding a provider means adding a class and one line here — controllers and
 * views never learn its name.
 */
final class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $gateways;

    public function __construct()
    {
        $this->gateways = [];

        /** @var list<class-string<PaymentGateway>> $drivers */
        $drivers = [PaystackGateway::class, StripeGateway::class, ManualBankTransferGateway::class];

        foreach ($drivers as $class) {
            $gateway = new $class;
            $this->gateways[$gateway->key()] = $gateway;
        }
    }

    public function get(string $key): PaymentGateway
    {
        return $this->gateways[$key]
            ?? throw new InvalidArgumentException("Unknown payment gateway [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->gateways[$key]);
    }

    /**
     * Gateways a customer can actually use for this currency right now.
     *
     * @return array<string, PaymentGateway>
     */
    public function availableFor(string $currency): array
    {
        return array_filter(
            $this->gateways,
            fn (PaymentGateway $gateway) => $gateway->isConfigured() && $gateway->supports($currency),
        );
    }

    /** @return array<string, PaymentGateway> */
    public function all(): array
    {
        return $this->gateways;
    }

    /** Preferred gateway for a currency, honouring the configured default. */
    public function defaultFor(string $currency): ?PaymentGateway
    {
        $available = $this->availableFor($currency);

        return $available[(string) config('billing.gateways.default')] ?? (reset($available) ?: null);
    }
}
