@props(['amount', 'currency' => 'USD'])
{{--
    Money is stored in minor units (kobo, cents) as integers. Formatting is
    centralised so no template ever divides by 100 by hand and gets it wrong.
--}}
@php
    $symbols = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'];
    $symbol = $symbols[strtoupper($currency)] ?? (strtoupper($currency).' ');
@endphp
<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ $symbol }}{{ number_format(((int) $amount) / 100, 2) }}</span>
