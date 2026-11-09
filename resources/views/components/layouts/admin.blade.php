@props(['title' => null, 'subtitle' => null])
{{--
    Operator console shell.

    Visually distinct from the customer app on purpose — an operator who cannot
    instantly tell which surface they are on will eventually take a destructive
    action in the wrong one. Deep obsidian and 24K warm amber, never the customer indigo.
--}}
@php
    $admin = auth('admin')->user();

    $nav = [
        'Overview' => [
            ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['route' => 'admin.system.index', 'label' => 'System health', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
        ],
        'Customers' => [
            ['route' => 'admin.tenants.index', 'label' => 'Workspaces', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
            ['route' => 'admin.users.index', 'label' => 'Users', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['route' => 'admin.suppressions.index', 'label' => 'Suppressions', 'icon' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'],
        ],
        'Revenue' => [
            ['route' => 'admin.invoices.index', 'label' => 'Billing', 'icon' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z'],
            ['route' => 'admin.plans.index', 'label' => 'Plans & pricing', 'icon' => 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z', 'super' => true],
        ],
        'Platform' => [
            ['route' => 'admin.activity.index', 'label' => 'Audit trail', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['route' => 'admin.settings.index', 'label' => 'Settings', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'super' => true],
            ['route' => 'admin.api-keys.index', 'label' => 'API keys', 'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z', 'super' => true],
            ['route' => 'admin.team.index', 'label' => 'Operators', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'super' => true],
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('platform.name') }} Operations</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full bg-[#07070c] font-sans text-slate-100 antialiased selection:bg-amber-500/30 selection:text-amber-200"
      x-data="{ mobileNav: false, commandOpen: false }">

<a href="#admin-main"
   class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:top-3 focus:left-3 focus:rounded-xl focus:bg-amber-500 focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-slate-950 shadow-lg">
    Skip to main content
</a>

<div class="flex min-h-full">

    {{-- ─────────────────── Sidebar ─────────────────── --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 flex-shrink-0 -translate-x-full flex-col border-r border-white/[0.08] bg-[#0a0a12] transition-transform duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] lg:static lg:flex lg:translate-x-0"
           :class="mobileNav ? 'translate-x-0 flex' : '-translate-x-full'">

        {{-- Brand / Header --}}
        <div class="flex h-16 flex-shrink-0 items-center justify-between border-b border-white/[0.08] px-5">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 via-amber-500 to-amber-600 shadow-[0_0_16px_-2px_rgba(245,158,11,0.5)]">
                    <svg class="h-4 w-4 text-slate-950 font-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold tracking-tight text-white">{{ config('platform.name') }}</p>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-amber-400">Operations</p>
                    </div>
                </div>
            </div>

            <button type="button" @click="mobileNav = false" class="p-1.5 text-slate-400 hover:text-white lg:hidden" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Navigation Menu --}}
        <nav class="flex-1 space-y-6 overflow-y-auto px-3.5 py-4 scrollbar-thin" aria-label="Operator navigation">
            @foreach($nav as $section => $items)
                @php
                    $visible = array_filter($items, fn ($i) => ! ($i['super'] ?? false) || $admin?->isSuperAdmin());
                @endphp
                @if($visible)
                    <div>
                        <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">{{ $section }}</p>
                        <div class="space-y-1">
                            @foreach($visible as $item)
                                @php $active = request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '.*', $item['route'])); @endphp
                                <a href="{{ route($item['route']) }}"
                                   @class([
                                       'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-semibold transition-all duration-150',
                                       'bg-amber-500/[0.12] text-amber-300 shadow-[inset_0_1px_0_0_rgba(245,158,11,0.15)] ring-1 ring-amber-500/25' => $active,
                                       'text-slate-400 hover:bg-white/[0.04] hover:text-white' => ! $active,
                                   ])
                                   @if($active) aria-current="page" @endif>
                                    <svg class="h-[18px] w-[18px] flex-shrink-0 transition-transform duration-150 group-hover:scale-110"
                                         @class(['text-amber-400' => $active, 'text-slate-400 group-hover:text-white' => ! $active])
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                                    </svg>
                                    <span class="truncate">{{ $item['label'] }}</span>
                                    @if($active)
                                        <span class="ml-auto h-1.5 w-1.5 rounded-full bg-amber-400 shadow-[0_0_6px_rgba(245,158,11,0.8)]"></span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        {{-- Operator Profile Footer --}}
        <div class="flex-shrink-0 border-t border-white/[0.08] bg-[#07070d]/60 p-3.5">
            <div class="flex items-center gap-3 rounded-xl bg-white/[0.03] p-2.5 border border-white/[0.05]">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500/20 to-amber-700/20 text-xs font-bold text-amber-300 ring-1 ring-amber-500/30">
                    {{ Str::of($admin?->name ?? '?')->substr(0, 1)->upper() }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-bold text-white">{{ $admin?->name }}</p>
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wider text-amber-400">{{ str_replace('_', ' ', $admin?->role ?? '') }}</p>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}" class="flex-shrink-0">
                    @csrf
                    <button type="submit"
                            title="Sign out of operator console"
                            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-white/10 hover:text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Mobile Scrim --}}
    <div x-show="mobileNav" x-cloak @click="mobileNav = false"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-30 bg-black/80 backdrop-blur-sm lg:hidden"></div>

    {{-- ─────────────────── Main Stage ─────────────────── --}}
    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Top App Bar --}}
        <header class="sticky top-0 z-20 flex h-16 flex-shrink-0 items-center justify-between gap-4 border-b border-white/[0.08] bg-[#090910]/85 px-4 backdrop-blur-2xl lg:px-8">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" @click="mobileNav = true"
                        class="-ml-1.5 rounded-xl p-2 text-slate-400 hover:bg-white/[0.06] hover:text-white transition lg:hidden"
                        aria-label="Open navigation">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <div class="min-w-0">
                    <h1 class="truncate text-base font-extrabold tracking-tight text-white">{{ $title ?? 'Operator Console' }}</h1>
                    @if($subtitle)
                        <p class="truncate text-[11px] text-slate-400">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                {{-- Universal Command Trigger (Cmd+K) --}}
                <button type="button"
                        @click="commandOpen = true"
                        class="flex items-center gap-2.5 rounded-xl border border-white/[0.08] bg-white/[0.03] px-3 py-1.5 text-xs text-slate-400 transition hover:border-amber-500/40 hover:bg-white/[0.06] hover:text-slate-200 shadow-sm">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span class="hidden sm:inline">Search platform…</span>
                    <span class="sm:hidden">Search</span>
                    <kbd class="hidden rounded bg-white/[0.06] px-1.5 py-0.5 font-mono text-[10px] text-slate-400 sm:inline border border-white/[0.08]">⌘K</kbd>
                </button>

                {{-- Direct Link to Client App --}}
                <a href="{{ url('/') }}"
                   class="hidden items-center gap-1.5 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3 py-1.5 text-xs font-semibold text-slate-400 transition hover:border-white/10 hover:bg-white/[0.05] hover:text-white md:inline-flex">
                    <span>Customer app</span>
                    <svg class="h-3 w-3 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>
            </div>
        </header>

        {{-- Main Content Canvas --}}
        <main id="admin-main" class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            {{-- Flash Feedback --}}
            @if (session('success'))
                <div class="mb-6 flex items-center justify-between gap-3 rounded-2xl border border-emerald-500/25 bg-emerald-500/[0.08] p-4 text-emerald-300 shadow-lg shadow-emerald-950/20" role="status" x-data="{ show: true }" x-show="show">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-300">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="text-xs font-semibold sm:text-sm">{{ session('success') }}</p>
                    </div>
                    <button type="button" @click="show = false" class="text-emerald-400/70 hover:text-emerald-300 p-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-500/30 bg-rose-500/[0.08] p-4 text-rose-300 shadow-lg shadow-rose-950/20" role="alert">
                    <div class="flex items-start gap-3">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-rose-500/20 text-rose-300">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold uppercase tracking-wider text-rose-400">Please review the following:</p>
                            <ul class="mt-1 space-y-1 text-xs">
                                @foreach($errors->all() as $error)<li>• {{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Secrets Reveal Banner (Once Only) --}}
            @foreach (['new_api_key' => 'API Key', 'new_admin_password' => 'Temporary Operator Password', 'new_user_password' => 'Temporary User Password'] as $key => $label)
                @if (session($key))
                    <div class="mb-6 rounded-2xl border-2 border-amber-500/40 bg-gradient-to-r from-amber-500/[0.12] to-amber-600/[0.06] p-5 shadow-2xl shadow-amber-950/30" role="alert"
                         x-data="{ copied: false }">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-300">
                                    Single-view secret
                                </span>
                                <p class="mt-2 text-sm font-extrabold text-white">{{ $label }} — copy it immediately</p>
                                <p class="mt-0.5 text-xs text-amber-200/70">This secret is stored hashed and will never be displayed again.</p>
                            </div>
                        </div>

                        <div class="mt-3.5 flex flex-wrap items-center gap-2">
                            <code class="flex-1 select-all break-all rounded-xl border border-white/10 bg-slate-950/80 px-4 py-2.5 font-mono text-xs font-bold text-amber-300">{{ session($key) }}</code>
                            <button type="button"
                                    @click="navigator.clipboard.writeText(@js(session($key))); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 px-4 py-2.5 text-xs font-bold text-slate-950 shadow-md transition hover:bg-amber-400 active:scale-95">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <span x-text="copied ? 'Copied to clipboard!' : 'Copy to clipboard'"></span>
                            </button>
                        </div>
                    </div>
                @endif
            @endforeach

            {{ $slot }}
        </main>
    </div>
</div>

{{-- ─────────────────── Universal Command Bar Modal (Cmd+K) ─────────────────── --}}
<div x-data="adminCommandPalette()"
     x-show="commandOpen"
     x-cloak
     @keydown.window.prevent.cmd.k="commandOpen = true"
     @keydown.window.prevent.ctrl.k="commandOpen = true"
     @keydown.escape.window="close()"
     class="relative z-50"
     role="dialog" aria-modal="true">

    {{-- Backdrop --}}
    <div x-show="commandOpen"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/80 backdrop-blur-md transition-opacity"
         @click="close()"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-20">
        <div x-show="commandOpen"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             @click.outside="close()"
             class="mx-auto max-w-xl transform overflow-hidden rounded-2xl border border-white/[0.1] bg-[#0c0c16] shadow-2xl shadow-black/80 transition-all">

            {{-- Search Input --}}
            <div class="relative border-b border-white/[0.08]">
                <svg class="pointer-events-none absolute left-4 top-4 h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       x-ref="searchInput"
                       x-model="term"
                       @input.debounce.200ms="run()"
                       @keydown.arrow-down.prevent="selectNext()"
                       @keydown.arrow-up.prevent="selectPrev()"
                       @keydown.enter.prevent="executeSelected()"
                       placeholder="Search workspaces, customer emails, invoices..."
                       class="h-13 w-full border-0 bg-transparent pl-12 pr-10 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-0">
                <button type="button" @click="close()" class="absolute right-3.5 top-3.5 rounded-lg p-1 text-slate-500 hover:text-slate-300">
                    <kbd class="rounded bg-white/[0.06] px-1.5 py-0.5 font-mono text-[10px] text-slate-400">ESC</kbd>
                </button>
            </div>

            {{-- Filter Category Pills --}}
            <div class="flex items-center gap-1.5 border-b border-white/[0.06] bg-white/[0.02] px-4 py-2 text-xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mr-1">Filter:</span>
                <button type="button" @click="filter = 'all'; run()"
                        :class="filter === 'all' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'text-slate-400 hover:bg-white/[0.04] border-transparent'"
                        class="rounded-lg border px-2 py-0.5 text-[11px] font-semibold transition">All</button>
                <button type="button" @click="filter = 'workspace'; run()"
                        :class="filter === 'workspace' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'text-slate-400 hover:bg-white/[0.04] border-transparent'"
                        class="rounded-lg border px-2 py-0.5 text-[11px] font-semibold transition">Workspaces</button>
                <button type="button" @click="filter = 'user'; run()"
                        :class="filter === 'user' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'text-slate-400 hover:bg-white/[0.04] border-transparent'"
                        class="rounded-lg border px-2 py-0.5 text-[11px] font-semibold transition">Users</button>
                <button type="button" @click="filter = 'invoice'; run()"
                        :class="filter === 'invoice' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'text-slate-400 hover:bg-white/[0.04] border-transparent'"
                        class="rounded-lg border px-2 py-0.5 text-[11px] font-semibold transition">Invoices</button>
            </div>

            {{-- Results Stage --}}
            <div class="max-h-80 overflow-y-auto p-2 scrollbar-thin">
                <template x-if="loading">
                    <div class="flex items-center justify-center py-8 text-xs text-slate-400 gap-2">
                        <svg class="h-4 w-4 animate-spin text-amber-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>Scanning platform database…</span>
                    </div>
                </template>

                <template x-if="!loading && results.length === 0 && term.trim().length >= 2">
                    <div class="py-8 text-center text-xs text-slate-500">
                        <p class="font-medium text-slate-400">No matching records found</p>
                        <p class="mt-1">Try another keyword, email domain, or invoice prefix.</p>
                    </div>
                </template>

                <template x-if="!loading && term.trim().length < 2">
                    <div class="p-4 text-xs text-slate-500">
                        <p class="font-semibold text-slate-400 uppercase tracking-wider text-[10px] mb-2">Quick Navigation</p>
                        <div class="grid grid-cols-2 gap-1.5">
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 rounded-xl p-2 text-slate-300 hover:bg-white/[0.04] transition">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                                <span>Overview Dashboard</span>
                            </a>
                            <a href="{{ route('admin.system.index') }}" class="flex items-center gap-2 rounded-xl p-2 text-slate-300 hover:bg-white/[0.04] transition">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                <span>System Health & Queues</span>
                            </a>
                            <a href="{{ route('admin.tenants.index') }}" class="flex items-center gap-2 rounded-xl p-2 text-slate-300 hover:bg-white/[0.04] transition">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                                <span>All Workspaces</span>
                            </a>
                            <a href="{{ route('admin.invoices.index') }}" class="flex items-center gap-2 rounded-xl p-2 text-slate-300 hover:bg-white/[0.04] transition">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                                <span>Billing & Wire Reviews</span>
                            </a>
                        </div>
                    </div>
                </template>

                <div class="space-y-1">
                    <template x-for="(result, index) in filteredResults()" :key="result.url + result.label">
                        <a :href="result.url"
                           @mouseenter="selectedIndex = index"
                           :class="selectedIndex === index ? 'bg-amber-500/[0.12] ring-1 ring-amber-500/30 text-white' : 'hover:bg-white/[0.04] text-slate-300'"
                           class="flex items-center justify-between gap-3 rounded-xl px-3.5 py-2.5 text-xs transition">
                            <div class="min-w-0 flex items-center gap-3">
                                <div :class="{
                                        'bg-amber-500/20 text-amber-400': result.type === 'Workspace',
                                        'bg-indigo-500/20 text-indigo-400': result.type === 'User',
                                        'bg-emerald-500/20 text-emerald-400': result.type === 'Invoice'
                                     }"
                                     class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg text-[10px] font-bold">
                                    <span x-text="result.type.substring(0, 1)"></span>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-white" x-text="result.label"></p>
                                    <p class="truncate text-[11px] text-slate-400" x-text="result.meta"></p>
                                </div>
                            </div>
                            <span class="rounded bg-white/[0.06] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400" x-text="result.type"></span>
                        </a>
                    </template>
                </div>
            </div>

            {{-- Footer Command Info --}}
            <div class="flex items-center justify-between border-t border-white/[0.06] bg-white/[0.02] px-4 py-2.5 text-[11px] text-slate-500">
                <div class="flex items-center gap-3">
                    <span><kbd class="font-mono text-slate-400">↑↓</kbd> to navigate</span>
                    <span><kbd class="font-mono text-slate-400">↵</kbd> to select</span>
                    <span><kbd class="font-mono text-slate-400">ESC</kbd> to exit</span>
                </div>
                <span class="text-amber-400 font-semibold">Elite Operations</span>
            </div>
        </div>
    </div>
</div>

{{-- Universal Confirmation Modal --}}
<x-admin.confirm-modal />

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    window.confirmAction = (options) => {
        window.dispatchEvent(new CustomEvent('open-confirm-modal', { detail: options }));
    };

    window.adminCommandPalette = () => ({
        term: '',
        results: [],
        filter: 'all',
        loading: false,
        selectedIndex: 0,
        init() {
            this.$watch('commandOpen', (value) => {
                if (value) {
                    this.term = '';
                    this.results = [];
                    this.selectedIndex = 0;
                    setTimeout(() => this.$refs.searchInput?.focus(), 50);
                }
            });
        },
        async run() {
            if (this.term.trim().length < 2) {
                this.results = [];
                return;
            }
            this.loading = true;
            try {
                const response = await fetch(@js(route('admin.search')) + '?q=' + encodeURIComponent(this.term), {
                    headers: { Accept: 'application/json' },
                });
                const json = await response.json();
                this.results = json.data ?? [];
                this.selectedIndex = 0;
            } catch (err) {
                this.results = [];
            } finally {
                this.loading = false;
            }
        },
        filteredResults() {
            if (this.filter === 'all') return this.results;
            return this.results.filter(r => r.type.toLowerCase() === this.filter);
        },
        selectNext() {
            const list = this.filteredResults();
            if (list.length === 0) return;
            this.selectedIndex = (this.selectedIndex + 1) % list.length;
        },
        selectPrev() {
            const list = this.filteredResults();
            if (list.length === 0) return;
            this.selectedIndex = (this.selectedIndex - 1 + list.length) % list.length;
        },
        executeSelected() {
            const list = this.filteredResults();
            if (list[this.selectedIndex]) {
                window.location.href = list[this.selectedIndex].url;
            }
        },
        close() {
            this.commandOpen = false;
        }
    });
</script>
@endpush

@stack('scripts')
</body>
</html>
