<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches), sidebarOpen: false }"
    x-init="$watch('darkMode', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light'); })"
    :class="{ 'dark': darkMode }"
    class="h-full"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    <meta name="description" content="EliteSender — Professional email campaign platform">

    <title>{{ isset($title) ? $title . ' · ' : '' }}EliteSender</title>

    {{-- PWA manifest --}}
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    {{-- Preconnect for fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Inline theme-flash prevention (must run before body renders) --}}
    <script>
        (function () {
            var t = localStorage.getItem('theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (t === 'dark' || (!t && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>

<body class="h-full bg-slate-50 dark:bg-slate-950 font-sans antialiased">

    {{-- ================================================================
         DESKTOP SIDEBAR + MAIN LAYOUT
         Sidebar: always visible lg+; collapses to icon-rail when sidebarOpen=false
         ================================================================ --}}
    <div class="flex h-full">

        {{-- Sidebar --}}
        <aside
            :class="sidebarOpen ? 'w-56' : 'w-16'"
            class="hidden lg:flex flex-col flex-shrink-0 transition-all duration-200 ease-out bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 overflow-hidden"
        >
            {{-- Logo --}}
            <div class="flex items-center h-16 px-4 border-b border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3 min-w-0">
                    {{-- Logomark --}}
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span x-show="sidebarOpen" x-transition class="text-[15px] font-semibold text-slate-900 dark:text-slate-100 truncate">EliteSender</span>
                </div>
            </div>

            {{-- Nav items --}}
            <nav class="flex-1 py-4 space-y-1 px-2 overflow-y-auto" aria-label="Main navigation">
                @php
                    $navItems = [
                        ['route' => 'dashboard',       'label' => 'Dashboard',  'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                        ['route' => 'campaigns.index', 'label' => 'Campaigns',  'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                        ['route' => 'contacts.index',  'label' => 'Contacts',   'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                        ['route' => '#',               'label' => 'Lists',      'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                        ['route' => 'smtp.index',      'label' => 'SMTP Pool',  'icon' => 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2'],
                        ['route' => '#',               'label' => 'Bounces',    'icon' => 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'],
                        ['route' => '#',               'label' => 'Settings',   'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
                    ];
                @endphp

                @foreach($navItems as $item)
                    @php $active = request()->routeIs(str_replace('#', '', $item['route']) . '*'); @endphp
                    <a
                        href="{{ $item['route'] === '#' ? '#' : route($item['route']) }}"
                        class="group flex items-center gap-3 px-2 py-2 rounded-lg text-[13px] font-medium transition-colors duration-150
                            {{ $active
                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100'
                            }}"
                        title="{{ $item['label'] }}"
                    >
                        <svg class="w-5 h-5 flex-shrink-0 {{ $active ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Sidebar toggle --}}
            <div class="p-2 border-t border-slate-200 dark:border-slate-800">
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="w-full flex items-center justify-center p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-slate-300 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    :aria-label="sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'"
                >
                    <svg class="w-5 h-5 transition-transform duration-200" :class="sidebarOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </aside>

        {{-- ============================================================
             MAIN CONTENT AREA
             ============================================================ --}}
        <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

            {{-- Top bar --}}
            <header class="flex items-center h-16 px-4 lg:px-6 gap-4 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex-shrink-0">

                {{-- Mobile menu button --}}
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    aria-label="Open navigation menu"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                {{-- Page title slot --}}
                <div class="flex-1 min-w-0">
                    @isset($header)
                        <h1 class="text-[15px] font-semibold text-slate-900 dark:text-slate-100 truncate">
                            {{ $header }}
                        </h1>
                    @endisset
                </div>

                {{-- Usage meter slot --}}
                @isset($usageMeter)
                    <div class="hidden sm:block">{{ $usageMeter }}</div>
                @endisset

                {{-- Notification bell --}}
                <button class="relative p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Notifications">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </button>

                {{-- Dark mode toggle --}}
                <button
                    @click="darkMode = !darkMode"
                    class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    :aria-label="darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
                >
                    <svg x-show="!darkMode" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                    </svg>
                    <svg x-show="darkMode" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                    </svg>
                </button>

                {{-- User avatar / menu --}}
                @auth
                    <div x-data="{ open: false }" class="relative">
                        <button
                            @click="open = !open"
                            @keydown.escape="open = false"
                            class="flex items-center gap-2 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-[13px] font-semibold text-indigo-700 dark:text-indigo-300">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </div>
                        </button>

                        <div
                            x-show="open"
                            @click.away="open = false"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 mt-1 w-48 rounded-xl bg-white dark:bg-slate-800 shadow-md border border-slate-200 dark:border-slate-700 py-1 z-50"
                        >
                            <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700">
                                <p class="text-[13px] font-medium text-slate-900 dark:text-slate-100 truncate">{{ auth()->user()->name }}</p>
                                <p class="text-[12px] text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="#" class="block px-3 py-2 text-[13px] text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Settings</a>
                            <form method="POST" action="/logout">
                                @csrf
                                <button type="submit" class="w-full text-left px-3 py-2 text-[13px] text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950">Sign out</button>
                            </form>
                        </div>
                    </div>
                @endauth
            </header>

            {{-- Page content --}}
            <main class="flex-1 overflow-y-auto" id="main-content" role="main">
                <div class="max-w-7xl mx-auto px-4 lg:px-6 py-6">
                    {{ $slot }}
                </div>
            </main>

        </div>
    </div>

    {{-- ================================================================
         MOBILE BOTTOM NAV (< lg) — docs/07-PWA-SPEC.md §2
         4 items + center FAB "New Campaign"
         ================================================================ --}}
    <nav
        class="lg:hidden fixed bottom-0 inset-x-0 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex items-center justify-around px-2 pb-safe z-40"
        style="padding-bottom: max(env(safe-area-inset-bottom), 8px);"
        aria-label="Bottom navigation"
    >
        @php
            $bottomNav = [
                ['route' => 'dashboard',       'label' => 'Home',      'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                ['route' => 'campaigns.index', 'label' => 'Campaigns', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ];
        @endphp

        {{-- Left items --}}
        @foreach($bottomNav as $item)
            @php $active = request()->routeIs(str_replace('#', '', $item['route']) . '*'); @endphp
            <a href="{{ $item['route'] === '#' ? '#' : route($item['route']) }}"
               class="flex flex-col items-center gap-1 px-3 py-2 min-h-[44px] {{ $active ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 dark:text-slate-400' }}"
            >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                </svg>
                <span class="text-[10px] font-medium">{{ $item['label'] }}</span>
            </a>
        @endforeach

        {{-- Center FAB — New Campaign --}}
        <a href="{{ route('campaigns.create') }}"
           class="flex flex-col items-center -mt-5"
           aria-label="New campaign"
        >
            <div class="w-14 h-14 rounded-full bg-indigo-600 flex items-center justify-center shadow-lg ring-4 ring-white dark:ring-slate-900">
                <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <span class="text-[10px] font-medium text-slate-500 dark:text-slate-400 mt-1">New</span>
        </a>

        <a href="{{ route('contacts.index') }}" class="flex flex-col items-center gap-1 px-3 py-2 min-h-[44px] text-slate-500 dark:text-slate-400 {{ request()->routeIs('contacts.*') ? 'text-indigo-600 dark:text-indigo-400' : '' }}">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-[10px] font-medium">Contacts</span>
        </a>

        <a href="#" class="flex flex-col items-center gap-1 px-3 py-2 min-h-[44px] text-slate-500 dark:text-slate-400">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-[10px] font-medium">Settings</span>
        </a>
    </nav>

    {{-- Toast container (x-toast) --}}
    <x-toast />

    {{-- SR live region for async announcements --}}
    <div id="sr-announce" aria-live="polite" aria-atomic="true" class="sr-only"></div>

    @stack('scripts')

</body>
</html>
