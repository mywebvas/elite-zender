@props(['title' => null, 'subtitle' => null])
{{--
    Operator console shell.

    Visually distinct from the customer app on purpose — an operator who cannot
    instantly tell which surface they are on will eventually take a destructive
    action in the wrong one. Amber and near-black, never the customer indigo.
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
<body class="h-full bg-[#07070c] font-sans text-slate-100 antialiased"
      x-data="{ mobileNav: false }">

<a href="#admin-main"
   class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:top-3 focus:left-3 focus:rounded-lg focus:bg-amber-500 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-950">
    Skip to main content
</a>

<div class="flex min-h-full">

    {{-- ─────────────────── Sidebar ─────────────────── --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 flex-shrink-0 -translate-x-full flex-col border-r border-white/[0.07] bg-[#0b0b14] transition-transform duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] lg:static lg:flex lg:translate-x-0"
           :class="mobileNav ? 'translate-x-0 flex' : '-translate-x-full'">

        <div class="flex h-16 flex-shrink-0 items-center gap-2.5 border-b border-white/[0.07] px-5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-amber-400 to-amber-600 shadow-[0_4px_12px_-4px_rgba(245,158,11,0.6)]">
                <svg class="h-4 w-4 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold tracking-[-0.012em]">{{ config('platform.name') }}</p>
                <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-amber-500">Operations</p>
            </div>
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto p-3" aria-label="Operator navigation">
            @foreach($nav as $section => $items)
                @php
                    $visible = array_filter($items, fn ($i) => ! ($i['super'] ?? false) || $admin?->isSuperAdmin());
                @endphp
                @if($visible)
                    <div>
                        <p class="px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-[0.1em] text-slate-600">{{ $section }}</p>
                        <div class="space-y-0.5">
                            @foreach($visible as $item)
                                @php $active = request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '.*', $item['route'])); @endphp
                                <a href="{{ route($item['route']) }}"
                                   @class([
                                       'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                                       'bg-amber-500/[0.12] text-amber-400' => $active,
                                       'text-slate-400 hover:bg-white/[0.04] hover:text-white' => ! $active,
                                   ])
                                   @if($active) aria-current="page" @endif>
                                    <svg class="h-[18px] w-[18px] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                                    </svg>
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="flex-shrink-0 border-t border-white/[0.07] p-3">
            <div class="flex items-center gap-2.5 px-2 pb-2">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-white/[0.06] text-xs font-semibold">
                    {{ Str::of($admin?->name ?? '?')->substr(0, 1)->upper() }}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">{{ $admin?->name }}</p>
                    <p class="truncate text-[11px] capitalize text-amber-500">{{ str_replace('_', ' ', $admin?->role ?? '') }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-400 transition-colors hover:bg-white/[0.04] hover:text-white">
                    Sign out
                </button>
            </form>
        </div>
    </aside>

    {{-- Mobile scrim --}}
    <div x-show="mobileNav" x-cloak @click="mobileNav = false" x-transition.opacity
         class="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm lg:hidden"></div>

    {{-- ─────────────────── Main ─────────────────── --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-16 flex-shrink-0 items-center gap-3 border-b border-white/[0.07] bg-[#0b0b14]/90 px-4 backdrop-blur-xl lg:px-6">
            <button type="button" @click="mobileNav = true"
                    class="-ml-1 rounded-lg p-2 text-slate-400 hover:bg-white/5 hover:text-white lg:hidden"
                    aria-label="Open navigation">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div class="min-w-0 flex-1">
                <h1 class="truncate text-[15px] font-semibold tracking-[-0.012em]">{{ $title ?? 'Operator console' }}</h1>
                @if($subtitle)
                    <p class="truncate text-xs text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>

            {{-- Global search. Support arrives with an email, a workspace name
                 or an invoice number and should not have to guess which page. --}}
            <div class="relative w-full max-w-xs"
                 x-data="adminSearch()" @keydown.escape.window="close()" @click.outside="close()">
                <label for="admin-search" class="sr-only">Search workspaces, users and invoices</label>
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input id="admin-search" type="search" x-model="term" @input.debounce.250ms="run()" @focus="run()"
                       placeholder="Search…" autocomplete="off"
                       class="w-full rounded-lg border-0 bg-white/[0.05] py-2 pl-9 pr-3 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500">

                <div x-show="open" x-cloak x-transition
                     class="absolute right-0 z-30 mt-2 w-80 overflow-hidden rounded-xl border border-white/10 bg-[#12121a] shadow-2xl">
                    <template x-if="loading">
                        <p class="px-4 py-3 text-xs text-slate-500">Searching…</p>
                    </template>
                    <template x-if="!loading && results.length === 0">
                        <p class="px-4 py-3 text-xs text-slate-500">Nothing matched “<span x-text="term"></span>”.</p>
                    </template>
                    <template x-for="result in results" :key="result.url + result.label">
                        <a :href="result.url" class="flex items-center justify-between gap-3 border-b border-white/5 px-4 py-2.5 text-sm transition hover:bg-white/5 last:border-0">
                            <span class="min-w-0">
                                <span class="block truncate font-medium" x-text="result.label"></span>
                                <span class="block truncate text-xs text-slate-500" x-text="result.meta"></span>
                            </span>
                            <span class="flex-shrink-0 rounded bg-white/5 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-slate-400" x-text="result.type"></span>
                        </a>
                    </template>
                </div>
            </div>

            <a href="{{ url('/') }}" class="hidden flex-shrink-0 text-xs font-medium text-slate-400 transition hover:text-white sm:block">
                Customer app ↗
            </a>
        </header>

        <main id="admin-main" class="flex-1 overflow-y-auto p-4 lg:p-6">
            @if (session('success'))
                <div class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/[0.08] p-4" role="status">
                    <p class="text-sm font-medium text-emerald-300">{{ session('success') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-rose-500/20 bg-rose-500/[0.08] p-4" role="alert">
                    <ul class="space-y-1 text-sm text-rose-300">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- Secrets shown exactly once. Rendered here so every page that
                 mints one gets identical, unmissable treatment. --}}
            @foreach (['new_api_key' => 'API key', 'new_admin_password' => 'Temporary password', 'new_user_password' => 'Temporary password'] as $key => $label)
                @if (session($key))
                    <div class="mb-4 rounded-xl border-2 border-amber-500/40 bg-amber-500/[0.08] p-4" role="alert"
                         x-data="{ copied: false }">
                        <p class="text-sm font-semibold text-amber-300">{{ $label }} — copy it now</p>
                        <p class="mt-0.5 text-xs text-amber-200/70">This is the only time it will be shown. It is stored hashed and cannot be recovered.</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <code class="flex-1 select-all break-all rounded-lg bg-slate-950/70 px-3 py-2 font-mono text-sm text-amber-100">{{ session($key) }}</code>
                            <button type="button"
                                    @click="navigator.clipboard.writeText(@js(session($key))); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-amber-400">
                                <span x-text="copied ? 'Copied' : 'Copy'"></span>
                            </button>
                        </div>
                    </div>
                @endif
            @endforeach

            {{ $slot }}
        </main>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    window.adminSearchData = () => ({
        term: '', results: [], open: false, loading: false,
        async run() {
            if (this.term.trim().length < 2) { this.open = false; this.results = []; return; }
            this.open = true;
            this.loading = true;
            try {
                const response = await fetch(@js(route('admin.search')) + '?q=' + encodeURIComponent(this.term), {
                    headers: { Accept: 'application/json' },
                });
                this.results = (await response.json()).data ?? [];
            } catch (error) {
                this.results = [];
            } finally {
                this.loading = false;
            }
        },
        close() { this.open = false; },
    });
    document.addEventListener('alpine:init', () => Alpine.data('adminSearch', window.adminSearchData));
    if (window.Alpine) window.Alpine.data('adminSearch', window.adminSearchData);
</script>
@endpush

@stack('scripts')
</body>
</html>
