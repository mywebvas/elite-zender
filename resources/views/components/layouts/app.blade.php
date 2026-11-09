<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches), sidebarOpen: window.innerWidth >= 1024, mobileMenuOpen: false }"
    x-init="$watch('darkMode', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light'); })"
    :class="{ 'dark': darkMode }"
    class="h-full"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    <meta name="description" content="{{ config('platform.name') }} — Professional email campaign platform">

    <title>{{ isset($title) ? $title . ' · ' : '' }}{{ config('platform.name') }}</title>

    {{-- PWA manifest --}}
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    {{-- Assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')

    {{-- Inline theme-flash prevention --}}
    <script nonce="{{ $cspNonce ?? '' }}">
        (function () {
            var t = localStorage.getItem('theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (t === 'dark' || (!t && prefersDark)) {
                document.documentElement.classList.add('dark');
                document.documentElement.style.backgroundColor = '#050508';
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.style.backgroundColor = '#F8FAFC';
            }
        })();
    </script>
</head>

<body class="h-full bg-slate-50 dark:bg-[#070A10] font-sans antialiased text-slate-900 dark:text-slate-100">

    @isset($impersonating)
        @if($impersonating)
            <div class="sticky top-0 z-[60] flex flex-wrap items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-slate-950" role="alert">
                <span>
                    Operator view — you are signed in as a customer
                    @if($impersonating['tenant']) in <strong>{{ $impersonating['tenant'] }}</strong>@endif.
                    Everything you do here is real and is being logged.
                </span>
                <form method="POST" action="{{ route('impersonate.stop') }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-slate-950 px-3 py-1 text-xs font-bold text-amber-400 hover:bg-slate-800">
                        Return to console
                    </button>
                </form>
            </div>
        @endif
    @endisset

    {{-- Accessibility skip link --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:z-[100] focus:top-3 focus:left-3 focus:rounded-lg focus:bg-indigo-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Skip to main content
    </a>

    {{-- Main Container --}}
    <div class="flex h-full">

        {{-- Desktop Collapsible Sidebar --}}
        <aside
            :class="sidebarOpen ? 'w-56' : 'w-16'"
            class="hidden lg:flex flex-col flex-shrink-0 transition-all duration-200 ease-out bg-[#1E1B4B] dark:bg-[#0A0E18] border-r border-white/5 dark:border-white/[0.06] overflow-hidden z-30"
        >
            {{-- Logomark Header --}}
            <div class="flex items-center h-16 px-4 border-b border-white/5 dark:border-white/[0.06]">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center flex-shrink-0 text-white">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span x-show="sidebarOpen" x-transition class="text-[14px] font-bold text-white tracking-tight truncate">{{ config('platform.name') }}</span>
                </div>
            </div>

            {{-- Nav Items --}}
            <nav class="flex-1 py-4 space-y-1 px-2 overflow-y-auto" aria-label="Main navigation">
                @php
                    $navItems = [
                        ['route' => 'dashboard',       'label' => 'Dashboard',  'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                        ['route' => 'campaigns.index', 'label' => 'Campaigns',  'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                        ['route' => 'automations.index', 'label' => 'Automations', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                        ['route' => 'contacts.index',  'label' => 'Contacts',   'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                        ['route' => 'lists.index',     'label' => 'Lists',      'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                        ['route' => 'smtp-accounts.index', 'label' => 'SMTP Pool', 'icon' => 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2'],
                        ['route' => 'bounces.index',   'label' => 'Bounces',    'icon' => 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'],
                        ['route' => 'billing.index',   'label' => 'Billing',    'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
                        ['route' => 'team.index',      'label' => 'Team',       'ability' => 'viewAny', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                        ['route' => 'settings.index',  'label' => 'Settings',   'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
                    ];
                @endphp

                @foreach($navItems as $item)
                    @continue(isset($item['ability']) && auth()->user()?->cannot($item['ability'], App\Models\User::class))
                    @php $active = request()->routeIs(str_replace('#', '', $item['route']) . '*'); @endphp
                    <a
                        href="{{ $item['route'] === '#' ? '#' : route($item['route']) }}"
                        class="group flex items-center gap-3 px-2.5 py-2 rounded-xl text-[13px] font-medium transition-all duration-150
                            {{ $active
                                ? 'bg-white/15 text-white font-semibold dark:bg-indigo-500/20 dark:text-indigo-300'
                                : 'text-indigo-100/80 hover:bg-white/10 hover:text-white dark:text-slate-400 dark:hover:bg-white/[0.04] dark:hover:text-slate-200'
                            }}"
                        title="{{ $item['label'] }}"
                    >
                        <svg class="w-5 h-5 flex-shrink-0 {{ $active ? 'text-white dark:text-indigo-400' : 'text-indigo-200/70 group-hover:text-white dark:text-slate-400 dark:group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Sidebar Collapse Toggle --}}
            <div class="p-2 border-t border-white/5 dark:border-white/[0.06]">
                <button
                    type="button"
                    @click="sidebarOpen = !sidebarOpen"
                    class="w-full flex items-center justify-center p-2 rounded-xl text-indigo-200/70 hover:text-white hover:bg-white/10 dark:text-slate-400 dark:hover:bg-white/[0.04] dark:hover:text-slate-300 transition-colors focus:outline-none"
                    :aria-label="sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'"
                >
                    <svg class="w-4 h-4 transition-transform duration-200" :class="sidebarOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </aside>

        {{-- Main Content Area --}}
        <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

            {{-- Sticky Header Bar --}}
            <header class="flex items-center h-16 px-4 lg:px-6 gap-4 bg-white/80 dark:bg-[#0A0E18]/80 backdrop-blur-md border-b border-slate-200/80 dark:border-white/[0.06] z-20 sticky top-0 flex-shrink-0">

                {{-- Mobile menu button --}}
                <button
                    type="button"
                    @click="mobileMenuOpen = true"
                    class="lg:hidden p-2 rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5 focus:outline-none"
                    aria-label="Open navigation menu"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                {{-- Page Title --}}
                <div class="flex-1 min-w-0">
                    @isset($header)
                        <h1 class="truncate text-[15px] font-bold tracking-tight text-slate-900 dark:text-white">
                            {{ $header }}
                        </h1>
                    @endisset
                </div>

                {{-- Usage meter slot --}}
                @isset($usageMeter)
                    <div class="hidden sm:block">{{ $usageMeter }}</div>
                @endisset

                {{-- Command Palette Trigger --}}
                <button
                    type="button"
                    @click="window.dispatchEvent(new CustomEvent('open-command-palette'))"
                    class="hidden md:flex items-center gap-2.5 px-3 py-1.5 text-xs text-slate-500 dark:text-slate-400 bg-slate-100/70 dark:bg-white/[0.04] hover:bg-slate-200/70 dark:hover:bg-white/[0.08] rounded-xl transition-colors border border-slate-200 dark:border-white/[0.08]"
                >
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <span>Quick jump…</span>
                    <kbd class="text-[10px] font-mono bg-white dark:bg-white/10 px-1.5 py-0.5 rounded border border-slate-200 dark:border-white/10 opacity-70">Ctrl K</kbd>
                </button>

                {{-- Dark Mode Toggle --}}
                <button
                    type="button"
                    @click="darkMode = !darkMode"
                    class="p-2 rounded-xl text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors"
                    :aria-label="darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
                >
                    <svg x-show="!darkMode" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                    </svg>
                    <svg x-show="darkMode" class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                    </svg>
                </button>

                {{-- User Avatar & Dropdown --}}
                @auth
                    <div x-data="{ open: false }" class="relative">
                        <button
                            type="button"
                            @click="open = !open"
                            @keydown.escape="open = false"
                            class="flex items-center gap-2 p-1 rounded-xl hover:bg-slate-100 dark:hover:bg-white/5 transition-colors"
                        >
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-xs font-bold text-white shadow-sm">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </div>
                        </button>

                        <div
                            x-show="open"
                            @click.away="open = false"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-52 rounded-2xl bg-white dark:bg-[#111624] shadow-xl border border-slate-200 dark:border-white/10 py-1.5 z-50"
                        >
                            <div class="px-3.5 py-2.5 border-b border-slate-100 dark:border-white/[0.06]">
                                <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ auth()->user()->name }}</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</p>
                            </div>
                            <div class="py-1">
                                <a href="{{ route('settings.index') }}" class="block px-3.5 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">Workspace Settings</a>
                                <a href="{{ route('billing.index') }}" class="block px-3.5 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">Plan & Billing</a>
                            </div>
                            <div class="border-t border-slate-100 dark:border-white/[0.06] pt-1">
                                <form method="POST" action="/logout">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-3.5 py-2 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">Sign out</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endauth
            </header>

            {{-- Main Content Window --}}
            <main class="flex-1 overflow-y-auto pb-24 lg:pb-0" id="main-content" role="main">

                @isset($billingNotice)
                    @if($billingNotice)
                        <div class="px-4 pt-4 lg:px-8">
                            <x-banner :tone="$billingNotice['tone']">
                                {{ $billingNotice['message'] }}
                                <x-slot:actions>
                                    <a href="{{ $billingNotice['action']['url'] ?? route('billing.index') }}"
                                       class="inline-flex items-center gap-1 rounded-lg bg-white/70 px-3 py-1.5 text-xs font-semibold shadow-sm transition hover:bg-white dark:bg-white/10 dark:hover:bg-white/20">
                                        {{ $billingNotice['action']['label'] ?? 'Go to billing' }}
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </x-slot:actions>
                            </x-banner>
                        </div>
                    @endif
                @endisset

                <div class="max-w-7xl mx-auto px-4 lg:px-6 py-6">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    {{-- Mobile Bottom Nav Bar (< lg) --}}
    <nav
        class="lg:hidden fixed bottom-0 inset-x-0 bg-white/90 dark:bg-[#0A0E18]/90 backdrop-blur-xl border-t border-slate-200/80 dark:border-white/10 flex items-center justify-around px-2 pb-safe z-40 transition-colors"
        style="padding-bottom: max(env(safe-area-inset-bottom), 8px);"
        aria-label="Bottom navigation"
    >
        @php
            $bottomNav = [
                ['route' => 'dashboard',       'label' => 'Home',      'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                ['route' => 'campaigns.index', 'label' => 'Campaigns', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ];
        @endphp

        @foreach($bottomNav as $item)
            @php $active = request()->routeIs(str_replace('#', '', $item['route']) . '*'); @endphp
            <a href="{{ $item['route'] === '#' ? '#' : route($item['route']) }}"
               class="flex flex-col items-center gap-1 px-3 py-2 min-h-[44px] {{ $active ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                </svg>
                <span class="text-[10px] font-medium">{{ $item['label'] }}</span>
            </a>
        @endforeach

        {{-- Center Floating Action Button (New Campaign) --}}
        <a href="{{ route('campaigns.create') }}"
           class="flex flex-col items-center -mt-5"
           aria-label="New campaign">
            <div class="w-13 h-13 rounded-2xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-500/30 ring-4 ring-white dark:ring-[#070A10]">
                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mt-1">New</span>
        </a>

        <a href="{{ route('contacts.index') }}" class="flex flex-col items-center gap-1 px-3 py-2 min-h-[44px] {{ request()->routeIs('contacts.*') ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-[10px] font-medium">Contacts</span>
        </a>

        <a href="{{ route('settings.index') }}" class="flex flex-col items-center gap-1 px-3 py-2 min-h-[44px] {{ request()->routeIs('settings.*') ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-[10px] font-medium">Settings</span>
        </a>
    </nav>

    {{-- Mobile Off-Canvas Drawer --}}
    <div x-show="mobileMenuOpen" x-cloak class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="mobileMenuOpen = false"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>

        <div class="fixed inset-0 flex">
            <div x-show="mobileMenuOpen"
                 x-transition:enter="transition ease-in-out duration-200 transform"
                 x-transition:enter-start="-translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in-out duration-200 transform"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="-translate-x-full"
                 class="relative mr-16 flex w-full max-w-xs flex-1 flex-col bg-[#1E1B4B] dark:bg-[#0A0E18] pb-4 pt-5 border-r border-white/10">
                <div class="absolute right-0 top-0 -mr-12 pt-2">
                    <button type="button" @click="mobileMenuOpen = false" class="ml-1 flex h-10 w-10 items-center justify-center rounded-full text-white focus:outline-none">
                        <span class="sr-only">Close sidebar</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="flex flex-shrink-0 items-center px-4 gap-3">
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center text-white">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <span class="text-[15px] font-bold text-white tracking-tight">{{ config('platform.name') }}</span>
                </div>
                <div class="mt-5 h-0 flex-1 overflow-y-auto">
                    <nav class="space-y-1 px-2">
                        @foreach($navItems as $item)
                        @continue(isset($item['ability']) && auth()->user()?->cannot($item['ability'], App\Models\User::class))
                        @php $active = request()->routeIs(str_replace('#', '', $item['route']) . '*'); @endphp
                        <a href="{{ $item['route'] === '#' ? '#' : route($item['route']) }}"
                           class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors
                           {{ $active ? 'bg-white/20 text-white font-bold' : 'text-indigo-100 hover:bg-white/10 hover:text-white' }}">
                            <svg class="h-5 w-5 flex-shrink-0 {{ $active ? 'text-white' : 'text-indigo-200' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                            </svg>
                            {{ $item['label'] }}
                        </a>
                        @endforeach
                    </nav>
                </div>
            </div>
        </div>
    </div>

    {{-- System Toast Container --}}
    <x-toast />

    {{-- SR live region --}}
    <div id="sr-announce" aria-live="polite" aria-atomic="true" class="sr-only"></div>

    {{-- Fuzzy Filtered Command Palette (Ctrl K / Cmd K) --}}
    <div x-data="{
            open: false,
            search: '',
            selectedIndex: 0,
            commands: [
                { name: 'Dashboard', url: @js(route('dashboard')), category: 'Navigation' },
                { name: 'Create Campaign', url: @js(route('campaigns.create')), category: 'Actions' },
                { name: 'Campaign Broadcasts', url: @js(route('campaigns.index')), category: 'Navigation' },
                { name: 'Subscribers & Contacts', url: @js(route('contacts.index')), category: 'Navigation' },
                { name: 'Contact Lists', url: @js(route('lists.index')), category: 'Navigation' },
                { name: 'Automations & Sequences', url: @js(route('automations.index')), category: 'Navigation' },
                { name: 'SMTP Relay Pool', url: @js(route('smtp-accounts.index')), category: 'Infrastructure' },
                { name: 'Bounce Shield Cleaner', url: @js(route('bounces.index')), category: 'Infrastructure' },
                { name: 'Plans & Billing', url: @js(route('billing.index')), category: 'Settings' },
                { name: 'Workspace Settings', url: @js(route('settings.index')), category: 'Settings' },
                { name: 'Two-Factor Authentication (2FA)', url: @js(route('settings.index')) + '#security', category: 'Security' },
                { name: 'Export Data & GDPR', url: @js(route('settings.index')) + '#data', category: 'Security' }
            ],
            get filteredCommands() {
                if (!this.search.trim()) return this.commands;
                const q = this.search.toLowerCase();
                return this.commands.filter(c => c.name.toLowerCase().includes(q) || c.category.toLowerCase().includes(q));
            },
            selectNext() {
                if (this.selectedIndex < this.filteredCommands.length - 1) this.selectedIndex++;
            },
            selectPrev() {
                if (this.selectedIndex > 0) this.selectedIndex--;
            },
            executeCurrent() {
                const cmd = this.filteredCommands[this.selectedIndex];
                if (cmd) {
                    window.location.href = cmd.url;
                }
            },
            init() {
                window.addEventListener('keydown', (e) => {
                    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                        e.preventDefault();
                        this.open = true;
                        this.search = '';
                        this.selectedIndex = 0;
                    }
                });
                window.addEventListener('open-command-palette', () => {
                    this.open = true;
                    this.search = '';
                    this.selectedIndex = 0;
                });
            }
         }"
         @keydown.escape.window="open = false"
         x-cloak
         class="relative z-50"
         role="dialog"
         aria-modal="true"
    >
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div x-show="open" class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-20">
            <div x-show="open"
                 @click.away="open = false"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="mx-auto max-w-xl transform divide-y divide-slate-100 dark:divide-white/10 overflow-hidden rounded-2xl bg-white dark:bg-[#111624] border border-slate-200 dark:border-white/10 shadow-2xl transition-all"
            >
                <div class="relative">
                    <svg class="pointer-events-none absolute left-4 top-3.5 h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input x-model="search" x-ref="searchInput"
                           @keydown.down.prevent="selectNext()"
                           @keydown.up.prevent="selectPrev()"
                           @keydown.enter.prevent="executeCurrent()"
                           x-effect="if(open) $nextTick(() => $refs.searchInput.focus())"
                           type="text" class="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-0 sm:text-sm"
                           placeholder="Search routes, campaigns, relays, settings…">
                </div>

                <ul class="max-h-72 scroll-py-2 overflow-y-auto py-2 text-sm text-slate-800 dark:text-slate-200" role="listbox">
                    <template x-for="(cmd, index) in filteredCommands" :key="cmd.url">
                        <li>
                            <a :href="cmd.url"
                               @mouseenter="selectedIndex = index"
                               class="flex items-center justify-between px-4 py-2.5 transition-colors cursor-pointer"
                               :class="selectedIndex === index ? 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 font-semibold' : 'text-slate-700 dark:text-slate-300'">
                                <span class="truncate" x-text="cmd.name"></span>
                                <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-slate-400" x-text="cmd.category"></span>
                            </a>
                        </li>
                    </template>
                    <li x-show="filteredCommands.length === 0" class="p-6 text-center text-xs text-slate-400">
                        No matching commands or pages found.
                    </li>
                </ul>

                <div class="px-4 py-2.5 bg-slate-50 dark:bg-white/[0.02] flex items-center justify-between text-[11px] text-slate-400">
                    <span><kbd class="font-mono bg-white dark:bg-white/10 px-1 py-0.5 rounded border border-slate-200 dark:border-white/10">↑</kbd> <kbd class="font-mono bg-white dark:bg-white/10 px-1 py-0.5 rounded border border-slate-200 dark:border-white/10">↓</kbd> to navigate</span>
                    <span><kbd class="font-mono bg-white dark:bg-white/10 px-1 py-0.5 rounded border border-slate-200 dark:border-white/10">↵</kbd> to select</span>
                    <span><kbd class="font-mono bg-white dark:bg-white/10 px-1 py-0.5 rounded border border-slate-200 dark:border-white/10">esc</kbd> to dismiss</span>
                </div>
            </div>
        </div>
    </div>

    @stack('scripts')

</body>
</html>
