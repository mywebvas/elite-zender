<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches) }"
    x-init="$watch('darkMode', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light'); })"
    :class="{ 'dark': darkMode }"
    class="h-full"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' · ' : '' }}EliteSender</title>
    <script nonce="{{ $cspNonce ?? '' }}">(function(){var t=localStorage.getItem('theme');if(t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark'); document.documentElement.style.backgroundColor = '#050508';}else{document.documentElement.classList.remove('dark'); document.documentElement.style.backgroundColor = '#ffffff';}})();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased bg-white dark:bg-[#050508] text-slate-900 dark:text-slate-100">

<div class="min-h-screen flex">

    {{-- ═══════════════════════════════════════════════
         LEFT BRAND PANEL (hidden on mobile)
    ═══════════════════════════════════════════════ --}}
    <div class="hidden lg:flex lg:w-[55%] xl:w-[60%] relative overflow-hidden flex-col"
         style="background: linear-gradient(145deg, #0a0a16 0%, #10102a 40%, #0d0d22 100%);">

        {{-- Grid overlay --}}
        <div class="absolute inset-0 grid-overlay opacity-60"></div>

        {{-- Radial glows --}}
        <div class="absolute top-0 left-0 w-[600px] h-[600px] opacity-20"
             style="background: radial-gradient(circle, #4f46e5 0%, transparent 70%); transform: translate(-30%, -30%);"></div>
        <div class="absolute bottom-0 right-0 w-[500px] h-[500px] opacity-15"
             style="background: radial-gradient(circle, #7c3aed 0%, transparent 70%); transform: translate(30%, 30%);"></div>

        {{-- Content --}}
        <div class="relative z-10 flex flex-col h-full p-12 xl:p-16">

            {{-- Logo --}}
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                     style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="text-white font-bold text-lg tracking-tight">EliteSender</span>
            </div>

            {{-- Center headline --}}
            <div class="flex-1 flex flex-col justify-center max-w-lg">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold text-indigo-300 border border-indigo-500/30 bg-indigo-500/10 mb-6 self-start">
                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse"></div>
                    THE INFRASTRUCTURE LAYER FOR EMAIL MARKETING
                </div>

                <h1 class="text-4xl xl:text-5xl font-black text-white leading-tight tracking-tight mb-6">
                    Stop Renting<br>
                    <span style="background: linear-gradient(135deg, #818cf8 0%, #a78bfa 50%, #c084fc 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        Your Audience.
                    </span>
                </h1>

                <p class="text-slate-400 text-lg leading-relaxed mb-10">
                    Send 100,000 emails per hour with unlimited SMTP rotation. Own your infrastructure permanently.
                </p>

                {{-- Feature list --}}
                <div class="space-y-3">
                    @foreach([
                        ['icon'=>'M13 10V3L4 14h7v7l9-11h-7z', 'label'=>'Up to 100,000 emails/hour'],
                        ['icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'label'=>'Automated bounce shield & suppression'],
                        ['icon'=>'M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'label'=>'Full data sovereignty — your server, your rules'],
                    ] as $f)
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-500/15 border border-indigo-500/20 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $f['icon'] }}"/>
                            </svg>
                        </div>
                        <span class="text-slate-300 text-sm">{{ $f['label'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Social proof --}}
            <div class="flex items-center gap-4 pt-6 border-t border-white/10">
                <div class="flex -space-x-2">
                    @foreach(['J','M','S','A','K'] as $i => $l)
                    <div class="w-8 h-8 rounded-full border-2 border-[#0a0a16] flex items-center justify-center text-[10px] font-bold text-white"
                         style="background: {{ ['#4f46e5','#7c3aed','#059669','#d97706','#0284c7'][$i] }};">{{ $l }}</div>
                    @endforeach
                </div>
                <div>
                    <div class="flex text-amber-400 text-sm">★★★★★</div>
                    <p class="text-slate-500 text-xs mt-0.5">Trusted by 2,400+ marketers</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         RIGHT FORM PANEL
    ═══════════════════════════════════════════════ --}}
    <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 sm:px-12 lg:px-16 bg-white dark:bg-[#050508]">

        {{-- Mobile logo --}}
        <div class="lg:hidden flex items-center gap-2 mb-10">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center"
                 style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <span class="font-bold text-lg">EliteSender</span>
        </div>

        {{-- Dark mode toggle --}}
        <div class="absolute top-6 right-6">
            <button @click="darkMode = !darkMode" class="p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors">
                <svg x-show="!darkMode" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                </svg>
                <svg x-show="darkMode" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                </svg>
            </button>
        </div>

        <div class="w-full max-w-sm">
            {{-- Heading --}}
            <div class="mb-8">
                <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ $header ?? 'Welcome back' }}
                </h2>
                @isset($subheader)
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $subheader }}</p>
                @endisset
            </div>

            {{-- Form content --}}
            {{ $slot }}
        </div>
    </div>
</div>

<x-toast />
</body>
</html>



