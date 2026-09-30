<?php

namespace App\Support;

/**
 * The country list used for billing.
 *
 * Deliberately short rather than an ISO 3166 dump: this exists to answer one
 * question — which currency do we invoice this workspace in — and a
 * 250-entry dropdown makes that decision harder, not easier. "Somewhere
 * else" is an honest option that maps to the default currency.
 *
 * Add a country here when a payment rail can actually settle in its
 * currency; listing one we cannot charge only produces unpayable invoices.
 */
final class Countries
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'NG' => 'Nigeria',
            'GH' => 'Ghana',
            'KE' => 'Kenya',
            'ZA' => 'South Africa',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'IE' => 'Ireland',
            'DE' => 'Germany',
            'FR' => 'France',
            'NL' => 'Netherlands',
            'IN' => 'India',
            'AE' => 'United Arab Emirates',
            'XX' => 'Somewhere else',
        ];
    }

    public static function name(?string $code): ?string
    {
        return self::all()[strtoupper((string) $code)] ?? null;
    }
}
