@props([
    'padding' => true,
    'variant' => 'default',
    'class'   => '',
])
@php
$variants = [
    'default'  => 'card',
    'elevated' => 'card-elevated',
    'bordered' => 'card-bordered',
];
$base = $variants[$variant] ?? 'card';
@endphp
<div {{ $attributes->merge(['class' => $base . ($padding ? ' p-6' : '') . ' ' . $class]) }}>
    {{ $slot }}
</div>
