@props(['title' => null])
{{--
    Operator console shell.

    Visually distinct from the customer app on purpose: an operator who cannot
    instantly tell which surface they are on will eventually take an action in
    the wrong one.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}EliteSender Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full bg-slate-950 font-sans text-slate-100 antialiased">

<a href="#admin-main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:top-3 focus:left-3 focus:rounded-lg focus:bg-amber-500 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-950">
    Skip to main content
</a>

<div class="flex min-h-full">
    <aside class="hidden w-60 flex-shrink-0 flex-col border-r border-white/10 bg-[#0b0b14] lg:flex">
        <div class="flex h-16 items-center gap-2.5 border-b border-white/10 px-5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500">
                <svg class="h-5 w-5 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-bold">EliteSender</p>
                <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-500">Operator console</p>
            </div>
        </div>

        <nav class="flex-1 space-y-1 p-3" aria-label="Admin navigation">
            @php
                $nav = [
                    ['route' => 'admin.dashboard', 'label' => 'Overview', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                    ['route' => 'admin.tenants.index', 'label' => 'Workspaces', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                    ['route' => 'admin.invoices.index', 'label' => 'Billing', 'icon' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z'],
                ];
                if (auth('admin')->user()?->isSuperAdmin()) {
                    $nav[] = ['route' => 'admin.plans.index', 'label' => 'Plans & pricing', 'icon' => 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z'];
                }
            @endphp

            @foreach($nav as $item)
                @php $active = request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '.*', $item['route'])); @endphp
                <a href="{{ route($item['route']) }}"
                   @class([
                       'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                       'bg-amber-500/15 text-amber-400' => $active,
                       'text-slate-400 hover:bg-white/5 hover:text-white' => ! $active,
                   ])>
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-3">
            <p class="truncate px-3 text-sm font-medium">{{ auth('admin')->user()?->name }}</p>
            <p class="mb-2 truncate px-3 text-xs text-amber-500">{{ str_replace('_', ' ', auth('admin')->user()?->role ?? '') }}</p>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-400 transition hover:bg-white/5 hover:text-white">
                    Sign out
                </button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 items-center justify-between gap-4 border-b border-white/10 bg-[#0b0b14] px-6">
            <h1 class="truncate text-base font-semibold">{{ $title ?? 'Operator console' }}</h1>
            <a href="{{ url('/') }}" class="text-xs font-medium text-slate-400 hover:text-white">Customer app ↗</a>
        </header>

        <main id="admin-main" class="flex-1 overflow-y-auto p-6">
            @if (session('success'))
                <div class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4" role="status">
                    <p class="text-sm font-medium text-emerald-300">{{ session('success') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-rose-500/20 bg-rose-500/10 p-4" role="alert">
                    <ul class="list-disc space-y-1 pl-5 text-sm text-rose-300">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>
