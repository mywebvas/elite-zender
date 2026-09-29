@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title . ' · ' : '' }}{{ config('platform.name') }}</title>

    {{-- Public landing pages (unsubscribe, preference centre) must render even
         if the asset bundle is unavailable, so the critical styling is inline
         and Vite only layers polish on top. --}}
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    </style>

    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <main class="flex min-h-screen items-center justify-center p-6">
        {{ $slot }}
    </main>
</body>
</html>
