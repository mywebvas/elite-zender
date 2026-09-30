<?php

namespace App\Platform;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Platform configuration an operator can change without a deploy.
 *
 * This is an *override layer*, not a replacement for config/env: anything not
 * set here falls through to `config()`. That ordering matters. A fresh install
 * with an empty settings table must behave exactly like it does today, and a
 * bad row in this table must never be able to take the platform down.
 *
 * Secrets (gateway keys) are encrypted at rest with the app key and are never
 * returned to a view — only a masked preview is.
 */
final class Settings
{
    private const CACHE_KEY = 'platform.settings';

    /** Keys whose value is a credential. */
    public const SECRET_KEYS = [
        'billing.gateways.paystack.secret_key',
        'billing.gateways.stripe.secret_key',
        'billing.gateways.stripe.webhook_secret',
    ];

    /**
     * Every setting an operator may edit, with the config key it overrides.
     *
     * Declared rather than free-form so the UI cannot invent a key that
     * nothing reads, and so an operator cannot set `app.key` from a web form.
     *
     * @return array<string, array{group: string, label: string, help: string, type: string}>
     */
    public static function schema(): array
    {
        return [
            // ── Platform ────────────────────────────────────────────────────
            'platform.name' => ['group' => 'platform', 'label' => 'Product name', 'help' => 'Shown in the interface and in outgoing mail.', 'type' => 'string'],
            'platform.support_email' => ['group' => 'platform', 'label' => 'Support email', 'help' => 'Where customers are told to write when something goes wrong.', 'type' => 'string'],
            'platform.signups_open' => ['group' => 'platform', 'label' => 'Allow new signups', 'help' => 'Turn off to close public registration without taking the site down. Existing customers are unaffected.', 'type' => 'bool'],
            'platform.lifecycle.enabled' => ['group' => 'platform', 'label' => 'Send lifecycle emails', 'help' => 'Trial, renewal, payment and usage notices. Turn off only while testing — customers stop being warned before they are charged or suspended.', 'type' => 'bool'],

            // ── Billing ─────────────────────────────────────────────────────
            'billing.trial_days' => ['group' => 'billing', 'label' => 'Trial length (days)', 'help' => 'Applies to workspaces created from now on.', 'type' => 'int'],
            'billing.grace_days' => ['group' => 'billing', 'label' => 'Grace period (days)', 'help' => 'How long an unpaid workspace keeps sending before it is paused.', 'type' => 'int'],
            'billing.default_currency' => ['group' => 'billing', 'label' => 'Default currency', 'help' => 'Used for every workspace outside the naira countries.', 'type' => 'string'],
            'billing.gateways.default' => ['group' => 'billing', 'label' => 'Preferred gateway', 'help' => 'Offered first at checkout when more than one can settle the currency.', 'type' => 'string'],

            // ── Paystack ────────────────────────────────────────────────────
            'billing.gateways.paystack.public_key' => ['group' => 'paystack', 'label' => 'Public key', 'help' => 'Safe to expose; used by the inline checkout.', 'type' => 'string'],
            'billing.gateways.paystack.secret_key' => ['group' => 'paystack', 'label' => 'Secret key', 'help' => 'Also signs incoming webhooks. Rotating it here takes effect immediately.', 'type' => 'secret'],
            'billing.gateways.paystack.currencies' => ['group' => 'paystack', 'label' => 'Currencies this account can charge', 'help' => 'Comma-separated, e.g. NGN. Most Nigerian accounts are NGN-only until USD is explicitly enabled — listing a currency the account cannot take produces invoices customers are unable to pay.', 'type' => 'list'],

            // ── Stripe ──────────────────────────────────────────────────────
            'billing.gateways.stripe.public_key' => ['group' => 'stripe', 'label' => 'Publishable key', 'help' => 'Safe to expose.', 'type' => 'string'],
            'billing.gateways.stripe.secret_key' => ['group' => 'stripe', 'label' => 'Secret key', 'help' => 'Setting this makes Stripe available at checkout straight away.', 'type' => 'secret'],
            'billing.gateways.stripe.webhook_secret' => ['group' => 'stripe', 'label' => 'Webhook signing secret', 'help' => 'From the Stripe dashboard endpoint for POST /webhooks/billing/stripe.', 'type' => 'secret'],
            'billing.gateways.stripe.currencies' => ['group' => 'stripe', 'label' => 'Currencies this account can charge', 'help' => 'Comma-separated, e.g. USD,EUR,GBP.', 'type' => 'list'],

            // ── Bank transfer ───────────────────────────────────────────────
            'billing.gateways.manual.enabled' => ['group' => 'bank', 'label' => 'Offer bank transfer', 'help' => 'Shown at checkout when account details exist for the currency.', 'type' => 'bool'],
            'billing.gateways.manual.accounts.NGN.bank_name' => ['group' => 'bank', 'label' => 'NGN — bank', 'help' => '', 'type' => 'string'],
            'billing.gateways.manual.accounts.NGN.account_name' => ['group' => 'bank', 'label' => 'NGN — account name', 'help' => '', 'type' => 'string'],
            'billing.gateways.manual.accounts.NGN.account_number' => ['group' => 'bank', 'label' => 'NGN — account number', 'help' => 'Leave blank to hide bank transfer for naira.', 'type' => 'string'],
            'billing.gateways.manual.accounts.USD.bank_name' => ['group' => 'bank', 'label' => 'USD — bank', 'help' => '', 'type' => 'string'],
            'billing.gateways.manual.accounts.USD.account_name' => ['group' => 'bank', 'label' => 'USD — account name', 'help' => '', 'type' => 'string'],
            'billing.gateways.manual.accounts.USD.account_number' => ['group' => 'bank', 'label' => 'USD — account number', 'help' => 'Leave blank to hide bank transfer for dollars.', 'type' => 'string'],
            'billing.gateways.manual.accounts.USD.swift' => ['group' => 'bank', 'label' => 'USD — SWIFT/BIC', 'help' => '', 'type' => 'string'],
        ];
    }

    /** @return array<string, string> */
    public static function groups(): array
    {
        return [
            'platform' => 'Platform',
            'billing' => 'Billing rules',
            'paystack' => 'Paystack',
            'stripe' => 'Stripe',
            'bank' => 'Bank transfer',
        ];
    }

    /**
     * Read an override, falling back to the compiled config.
     *
     * Never throws: a malformed row returns the config value instead of
     * bringing down every request that touches it.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $overrides = $this->all();

        if (! array_key_exists($key, $overrides)) {
            return config($key, $default);
        }

        return $overrides[$key] ?? config($key, $default);
    }

    /** @param array<string, mixed> $values */
    public function put(array $values): void
    {
        $schema = self::schema();

        foreach ($values as $key => $value) {
            if (! isset($schema[$key])) {
                continue; // never persist a key nothing reads
            }

            $type = $schema[$key]['type'];

            // An empty secret means "leave it alone", never "erase it" —
            // masked fields always post blank.
            if ($type === 'secret' && ($value === null || $value === '')) {
                continue;
            }

            // Same for a list. The settings form posts every field it
            // renders, so a blank currencies box would otherwise store an
            // empty array and silently stop the gateway supporting anything
            // at all. Clearing one is what the reset action is for.
            if ($type === 'list' && self::parseList($value) === []) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $this->encode($value, $type),
                    'type' => $type,
                    'group' => $schema[$key]['group'],
                    'is_secret' => $type === 'secret',
                ],
            );
        }

        $this->flush();
    }

    public function forget(string $key): void
    {
        Setting::whereKey($key)->delete();
        $this->flush();
    }

    /**
     * All overrides, decoded.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): array {
            $decoded = [];

            foreach (Setting::all() as $setting) {
                $decoded[$setting->key] = $this->decode($setting->getRawOriginal('value'), $setting->type);
            }

            return $decoded;
        });
    }

    /**
     * Values for the settings form: secrets become a masked preview so an
     * operator can confirm *which* key is installed without it being readable
     * over someone's shoulder or in a screenshot.
     *
     * @return array<string, mixed>
     */
    public function forDisplay(): array
    {
        $display = [];

        foreach (self::schema() as $key => $meta) {
            $value = $this->get($key);

            $display[$key] = match ($meta['type']) {
                'secret' => ['set' => filled($value), 'preview' => $this->mask((string) $value)],
                'list' => implode(', ', (array) ($value ?? [])),
                default => $value,
            };
        }

        return $display;
    }

    /** Push every override into the live config for this request. */
    public function apply(): void
    {
        $overrides = $this->all();

        if ($overrides === []) {
            return;
        }

        config($overrides);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function encode(mixed $value, string $type): ?string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'int' => (string) (int) $value,
            // Stored as JSON so `config()` receives a real array: a
            // comma-separated string would silently fail every
            // `in_array()` check that reads it.
            'list' => json_encode(self::parseList($value)),
            'json' => json_encode($value),
            'secret' => Crypt::encryptString((string) $value),
            default => $value === null ? null : (string) $value,
        };
    }

    private function decode(?string $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        try {
            return match ($type) {
                'bool' => $raw === '1',
                'int' => (int) $raw,
                'list' => is_array($decoded = json_decode($raw, true)) ? $decoded : [],
                'json' => json_decode($raw, true),
                'secret' => Crypt::decryptString($raw),
                default => $raw,
            };
        } catch (Throwable) {
            // A secret encrypted under a rotated APP_KEY is unreadable. Falling
            // back to config beats a fatal on every request.
            return null;
        }
    }

    /**
     * Split operator input into a clean, upper-cased list.
     *
     * @return list<string>
     */
    public static function parseList(mixed $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = explode(',', (string) $value);
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($part) => strtoupper(trim((string) $part)),
            $parts,
        ), static fn (string $part) => $part !== '')));
    }

    private function mask(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return mb_strlen($value) <= 8
            ? str_repeat('•', mb_strlen($value))
            : mb_substr($value, 0, 4).str_repeat('•', 12).mb_substr($value, -4);
    }
}
