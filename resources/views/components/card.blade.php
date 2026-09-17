@props([
    'padding' => true,
    'class'   => '',
])

<div {{ $attributes->merge(['class' => 'rounded-xl shadow-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 ' . ($padding ? 'p-6' : '') . ' ' . $class]) }}>
    {{ $slot }}
</div>
