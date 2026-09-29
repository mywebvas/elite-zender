<?php

namespace App\Lifecycle;

/**
 * Minor-unit money, formatted for humans.
 *
 * The Blade `<x-money>` component owns this for the interface; emails and
 * log lines need the same answer without a view. One implementation, so a
 * receipt and the billing page can never disagree about what was charged.
 */
final class Money
{
    /** @var array<string, string> */
    private const SYMBOLS = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'];

    public static function format(int $minorUnits, string $currency): string
    {
        $code = strtoupper($currency);
        $symbol = self::SYMBOLS[$code] ?? $code.' ';

        return $symbol.number_format($minorUnits / 100, 2);
    }
}
