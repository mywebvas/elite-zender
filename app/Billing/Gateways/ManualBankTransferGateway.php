<?php

namespace App\Billing\Gateways;

use App\Billing\CheckoutSession;
use App\Billing\Contracts\PaymentGateway;
use App\Billing\PaymentResult;
use App\Billing\StoredCredential;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Offline bank transfer.
 *
 * There is no API to call: the customer is shown account details and a unique
 * reference, uploads proof, and an operator confirms it in the admin panel.
 * `verify()` therefore always reports "pending" — the only thing that can mark
 * this paid is a human decision, recorded against their admin id.
 *
 * This matters in markets where card acceptance is unreliable or where finance
 * departments simply will not pay any other way.
 */
final class ManualBankTransferGateway implements PaymentGateway
{
    /** No O/0, I/1, S/5 or B/8 — references get dictated over the phone. */
    private const ALPHABET = 'ACDEFGHJKLMNPQRTUVWXYZ2346789';

    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Bank transfer (manual confirmation)';
    }

    public function isConfigured(): bool
    {
        if (! config('billing.gateways.manual.enabled')) {
            return false;
        }

        // Pointless to offer if no operator has entered account details.
        foreach ((array) config('billing.gateways.manual.accounts', []) as $account) {
            if (filled($account['account_number'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    public function supports(string $currency): bool
    {
        return filled($this->account($currency)['account_number'] ?? null);
    }

    public function checkout(Invoice $invoice, string $callbackUrl): CheckoutSession
    {
        // Short, unambiguous, and safe to read down a phone line.
        //
        // Built from an explicit alphabet rather than by filtering
        // Str::random(): uppercasing *after* a filter turns a lowercase 'o'
        // straight back into the 'O' the filter just removed.
        $reference = 'EZ'.collect(range(1, 10))
            ->map(fn (): string => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
            ->implode('');

        $account = $this->account($invoice->currency);

        return new CheckoutSession(
            gateway: $this->key(),
            reference: $reference,
            instructions: array_filter([
                'Bank' => $account['bank_name'] ?? '',
                'Account name' => $account['account_name'] ?? '',
                'Account number' => $account['account_number'] ?? '',
                'SWIFT/BIC' => $account['swift'] ?? '',
                'IBAN' => $account['iban'] ?? '',
                'Amount' => $invoice->currency.' '.number_format($invoice->balance() / 100, 2),
                'Payment reference' => $reference,
            ], static fn ($value) => $value !== ''),
        );
    }

    public function verify(string $reference): PaymentResult
    {
        return PaymentResult::failure(
            $this->key(),
            'Bank transfers are confirmed by an operator once the funds land.',
            $reference,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): ?PaymentResult
    {
        return null;
    }

    /**
     * There is nothing to re-charge: a renewal on this rail means issuing an
     * invoice and asking the customer to transfer again.
     */
    public function supportsRecurring(): bool
    {
        return false;
    }

    public function chargeStored(Invoice $invoice, StoredCredential $credential): PaymentResult
    {
        return PaymentResult::failure(
            $this->key(),
            'Bank transfers cannot be charged automatically.',
        );
    }

    /** @return array<string, string> */
    public function account(string $currency): array
    {
        return (array) config('billing.gateways.manual.accounts.'.strtoupper($currency), []);
    }
}
