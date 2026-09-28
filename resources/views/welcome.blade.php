<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches) }"
    x-init="$watch('darkMode', v => { document.documentElement.classList.toggle('dark', v); localStorage.setItem('theme', v ? 'dark' : 'light'); })"
    :class="{ 'dark': darkMode }"
    class="scroll-smooth"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Send 100,000 emails per hour with unlimited SMTP rotation. Own your email infrastructure permanently. No recurring SaaS fees.">
    <title>EliteSender — Own Your Email Infrastructure</title>
    <script>(function(){var t=localStorage.getItem('theme');if(t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark'); document.documentElement.style.backgroundColor = '#050508';}else{document.documentElement.classList.remove('dark'); document.documentElement.style.backgroundColor = '#ffffff';}})();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white dark:bg-[#050508] text-slate-900 dark:text-white overflow-x-hidden">

{{-- --------------------------- NAV --------------------------- --}}
<nav class="fixed top-0 inset-x-0 z-50 glass border-b border-white/10 dark:border-white/[0.05]">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:linear-gradient(135deg,#4f46e5,#7c3aed)">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <span class="font-black text-lg tracking-tight">EliteSender</span>
        </div>
        <div class="hidden md:flex items-center gap-6">
            <a href="#features" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">Features</a>
            <a href="#pricing" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">Pricing</a>
            <a href="#faq" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">FAQ</a>
        </div>
        <div class="flex items-center gap-3">
            <button @click="darkMode = !darkMode" class="p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors">
                <svg x-show="!darkMode" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/></svg>
                <svg x-show="darkMode"  class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
            </button>
            @auth
            <a href="{{ url('/dashboard') }}" class="text-sm font-semibold text-slate-700 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400">Dashboard ?</a>
            @else
            <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors">Log in</a>
            <a href="{{ route('register') }}" class="btn-gradient text-sm py-2 px-4">Get started free</a>
            @endauth
        </div>
    </div>
</nav>

{{-- --------------------------- HERO --------------------------- --}}
<section class="relative min-h-screen flex items-center justify-center pt-[calc(64px+90px)] pb-24 overflow-hidden"
         style="background:linear-gradient(180deg, #000008 0%, #050518 60%, #080520 100%)">
    <div class="absolute inset-0 grid-overlay opacity-40"></div>
    <div class="absolute top-0 left-0 w-[800px] h-[800px] opacity-25" style="background:radial-gradient(circle,#4f46e5 0%,transparent 65%);transform:translate(-25%,-30%)"></div>
    <div class="absolute bottom-0 right-0 w-[600px] h-[600px] opacity-20" style="background:radial-gradient(circle,#7c3aed 0%,transparent 65%);transform:translate(25%,30%)"></div>

    <div class="relative z-10 max-w-5xl mx-auto px-6 text-center">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 text-indigo-300 text-xs font-bold uppercase tracking-widest mb-8">
            <div class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse"></div>
            The Infrastructure Layer for Modern Email Marketing
        </div>

        <h1 class="text-6xl sm:text-7xl lg:text-[5.5rem] font-black text-white leading-[1.08] tracking-tight mb-6">
            Stop Renting<br>
            <span style="background:linear-gradient(135deg,#818cf8 0%,#a78bfa 40%,#c084fc 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Your Audience.</span>
        </h1>

        <p class="text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed mb-4">
            Send <strong class="text-white">100,000 emails per hour</strong> using unlimited SMTP rotation and intelligent micro-batching. Own your infrastructure permanently.
        </p>
        <p class="text-slate-500 mb-10">Zero recurring SaaS fees. Your server. Your rules. Your data.</p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('register') }}" class="btn-gradient py-4 px-8 text-base font-black">
                Secure Lifetime Access — $79
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
            <a href="{{ route('register') }}" class="px-8 py-4 text-base font-semibold text-slate-300 hover:text-white border border-white/20 hover:border-white/40 rounded-xl transition-all">
                Try Free Version
            </a>
        </div>

        <p class="mt-6 text-sm text-slate-600">? No credit card required &nbsp;·&nbsp; ? 14-day money-back guarantee &nbsp;·&nbsp; ? Instant access</p>

                <div class="mt-20 relative mx-auto max-w-5xl" x-intersect="$el.classList.add('opacity-100', 'translate-y-0'); $el.classList.remove('opacity-0', 'translate-y-8')" class="transition-all duration-1000 ease-out opacity-0 translate-y-8">
            <div class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-[2rem] blur-2xl opacity-20"></div>
            <div class="relative rounded-[2rem] border border-white/10 bg-[#080810]/90 backdrop-blur-3xl shadow-2xl overflow-hidden aspect-[16/9] flex flex-col">
                <div class="h-14 border-b border-white/5 flex items-center px-6 gap-4">
                    <div class="flex gap-2"><div class="w-3 h-3 rounded-full bg-slate-700"></div><div class="w-3 h-3 rounded-full bg-slate-700"></div><div class="w-3 h-3 rounded-full bg-slate-700"></div></div>
                    <div class="h-6 w-64 bg-white/5 rounded-full mx-auto"></div>
                </div>
                <div class="flex-1 flex p-6 gap-6">
                    <div class="w-48 hidden sm:flex flex-col gap-4">
                        <div class="h-8 bg-white/5 rounded-lg w-full"></div>
                        <div class="h-8 bg-white/5 rounded-lg w-3/4"></div>
                        <div class="h-8 bg-white/5 rounded-lg w-5/6"></div>
                        <div class="h-8 bg-white/5 rounded-lg w-4/5 mt-auto"></div>
                    </div>
                    <div class="flex-1 flex flex-col gap-6">
                        <div class="flex gap-6">
                            <div class="h-24 bg-white/5 rounded-2xl flex-1 border border-white/5"></div>
                            <div class="h-24 bg-white/5 rounded-2xl flex-1 border border-white/5"></div>
                            <div class="h-24 bg-white/5 rounded-2xl flex-1 border border-white/5"></div>
                        </div>
                        <div class="flex-1 bg-white/5 rounded-2xl border border-white/5"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Social proof strip --}}
        <div class="mt-12 flex flex-wrap items-center justify-center gap-6 text-slate-600 text-sm">
            <span>Replacing:</span>
            @foreach(['Mailchimp','Instantly','ActiveCampaign','Klaviyo','SendGrid'] as $brand)
            <span class="px-3 py-1 rounded-full border border-white/10 bg-white/5 text-slate-400 line-through">{{ $brand }}</span>
            @endforeach
        </div>
    </div>
</section>

{{-- --------------------------- STATS --------------------------- --}}
<section class="py-16 border-y border-slate-200 dark:border-white/[0.05] bg-slate-50 dark:bg-[#080810]">
    <div class="max-w-5xl mx-auto px-6 grid grid-cols-2 lg:grid-cols-4 gap-8">
        @foreach([
            ['100k','Emails / hour throughput'],
            ['8',   'SMTP accounts supported'],
            ['$79',  'One-time lifetime price'],
            ['0',    'Monthly recurring fees'],
        ] as $s)
        <div class="text-center">
            <p class="text-4xl font-black gradient-text">{{ $s[0] }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">{{ $s[1] }}</p>
        </div>
        @endforeach
    </div>
</section>

{{-- --------------------------- FEATURES --------------------------- --}}
<section id="features" class="py-32 bg-white dark:bg-[#050508]">
    <div class="max-w-6xl mx-auto px-6">
        <div class="text-center mb-24" x-intersect="$el.classList.add('opacity-100', 'translate-y-0'); $el.classList.remove('opacity-0', 'translate-y-8')" class="transition-all duration-1000 ease-out opacity-0 translate-y-8">
            <h2 class="text-5xl md:text-6xl font-black text-slate-900 dark:text-white tracking-tight">Built to dominate.</h2>
            <p class="mt-6 text-xl text-slate-500 dark:text-slate-400 max-w-2xl mx-auto">Military-grade delivery infrastructure with absolute premium simplicity.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 auto-rows-[24rem]">
            {{-- Bento 1: Spans 2 cols --}}
            <div class="md:col-span-2 relative rounded-3xl p-10 overflow-hidden bg-slate-50 dark:bg-white/[0.02] border border-slate-200 dark:border-white/5 group hover:border-indigo-500/50 transition-colors" x-intersect="$el.classList.add('opacity-100', 'translate-y-0'); $el.classList.remove('opacity-0', 'translate-y-8')" class="transition-all duration-1000 delay-100 ease-out opacity-0 translate-y-8">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="relative z-10 h-full flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500 text-white flex items-center justify-center shadow-lg mb-6 shadow-indigo-500/30">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white mb-3">Parallel Delivery Engine</h3>
                        <p class="text-slate-500 dark:text-slate-400 max-w-sm text-lg">Send up to 100,000 emails per hour using our multi-threaded asynchronous dispatch architecture.</p>
                    </div>
                </div>
            </div>

            {{-- Bento 2: 1 col --}}
            <div class="md:col-span-1 relative rounded-3xl p-10 overflow-hidden bg-slate-50 dark:bg-white/[0.02] border border-slate-200 dark:border-white/5 group hover:border-violet-500/50 transition-colors" x-intersect="$el.classList.add('opacity-100', 'translate-y-0'); $el.classList.remove('opacity-0', 'translate-y-8')" class="transition-all duration-1000 delay-200 ease-out opacity-0 translate-y-8">
                <div class="absolute inset-0 bg-gradient-to-bl from-violet-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="relative z-10 h-full flex flex-col">
                    <div class="w-12 h-12 rounded-2xl bg-violet-500 text-white flex items-center justify-center shadow-lg mb-6 shadow-violet-500/30">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-3">SMTP Rotation</h3>
                    <p class="text-slate-500 dark:text-slate-400">Distribute your sending loads dynamically across unlimited Amazon SES or SendGrid accounts.</p>
                </div>
            </div>

            {{-- Bento 3: 1 col --}}
            <div class="md:col-span-1 relative rounded-3xl p-10 overflow-hidden bg-slate-50 dark:bg-white/[0.02] border border-slate-200 dark:border-white/5 group hover:border-emerald-500/50 transition-colors" x-intersect="$el.classList.add('opacity-100', 'translate-y-0'); $el.classList.remove('opacity-0', 'translate-y-8')" class="transition-all duration-1000 delay-100 ease-out opacity-0 translate-y-8">
                <div class="absolute inset-0 bg-gradient-to-tr from-emerald-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="relative z-10 h-full flex flex-col">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500 text-white flex items-center justify-center shadow-lg mb-6 shadow-emerald-500/30">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white mb-3">Bounce Shield</h3>
                    <p class="text-slate-500 dark:text-slate-400">Automated IMAP scanning suppresses invalid addresses to protect reputation.</p>
                </div>
            </div>

            {{-- Bento 4: Spans 2 cols --}}
            <div class="md:col-span-2 relative rounded-3xl p-10 overflow-hidden bg-slate-50 dark:bg-white/[0.02] border border-slate-200 dark:border-white/5 group hover:border-sky-500/50 transition-colors" x-intersect="$el.classList.add('opacity-100', 'translate-y-0'); $el.classList.remove('opacity-0', 'translate-y-8')" class="transition-all duration-1000 delay-200 ease-out opacity-0 translate-y-8">
                <div class="absolute inset-0 bg-gradient-to-tl from-sky-500/10 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="relative z-10 h-full flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-sky-500 text-white flex items-center justify-center shadow-lg mb-6 shadow-sky-500/30">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                        </div>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white mb-3">1-Click Smart Retargeting</h3>
                        <p class="text-slate-500 dark:text-slate-400 max-w-sm text-lg">Re-send exclusively to non-openers with a new subject line in a single click. Zero duplicate sends.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- --------------------------- OLD WAY VS NEW WAY --------------------------- --}}
<section class="py-20 bg-slate-50 dark:bg-[#080810]">
    <div class="max-w-5xl mx-auto px-6">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-black text-slate-900 dark:text-white">The Paradigm Shift</h2>
            <p class="mt-3 text-slate-500">Stop renting. Start owning.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="relative rounded-3xl p-8 bg-white dark:bg-[#080810] border border-slate-200 dark:border-white/5 border-rose-200 dark:border-rose-500/20 ring-rose-200 dark:ring-rose-500/20">
                <h3 class="font-black text-rose-600 dark:text-rose-400 mb-5 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-500/15 flex items-center justify-center text-xs">?</span>
                    The Old Way (Renting)
                </h3>
                @foreach(['Monthly subscriptions that grow with your list','Artificial sending limits per tier','Account suspension risk — no warning','Your data stored on their platform','Escalating fees: $49 ? $99 ? $199/mo'] as $item)
                <div class="flex items-start gap-3 mb-3">
                    <span class="text-rose-400 font-bold text-sm mt-0.5">?</span>
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $item }}</p>
                </div>
                @endforeach
            </div>
            <div class="relative rounded-3xl p-8 bg-white dark:bg-[#080810] border border-slate-200 dark:border-white/5 ring-emerald-200 dark:ring-emerald-500/20" style="background:linear-gradient(135deg,rgba(79,70,229,0.03),rgba(124,58,237,0.03))">
                <h3 class="font-black text-emerald-600 dark:text-emerald-400 mb-5 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-500/15 flex items-center justify-center text-xs">?</span>
                    The New Way (Owning)
                </h3>
                @foreach(['Pay once — $79 lifetime. Done.','Send without limits — 100k/hr','Your server. Your SMTP. Your rules.','Full data sovereignty — JSON export anytime','List grows to 1M? Costs stay exactly the same.'] as $item)
                <div class="flex items-start gap-3 mb-3">
                    <span class="text-emerald-500 font-bold text-sm mt-0.5">?</span>
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $item }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- --------------------------- PRICING --------------------------- --}}
<section id="pricing" class="py-24 bg-white dark:bg-[#050508]">
    <div class="max-w-5xl mx-auto px-6">
        <div class="text-center mb-14">
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-3">Simple Pricing</p>
            <h2 class="text-4xl font-black text-slate-900 dark:text-white">Own it once. Use it forever.</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
            {{-- Free --}}
            <div class="relative rounded-3xl p-8 bg-white dark:bg-[#080810] border border-slate-200 dark:border-white/5">
                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Free Engine</p>
                <p class="text-4xl font-black text-slate-900 dark:text-white mb-1">$0</p>
                <p class="text-sm text-slate-500 mb-6">Explore the platform</p>
                <hr class="border-slate-200 dark:border-white/10 mb-6">
                @foreach(['1 SMTP connection','Standard sending engine','Basic analytics'] as $f)
                <div class="flex items-center gap-2 mb-3 text-sm text-slate-600 dark:text-slate-300">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    {{ $f }}
                </div>
                @endforeach
                <a href="{{ route('register') }}" class="mt-6 block text-center py-3 px-6 rounded-xl border border-slate-200 dark:border-white/10 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                    Start Free
                </a>
            </div>

            {{-- Lifetime MOST POPULAR --}}
            <div class="relative rounded-2xl p-8 text-white overflow-hidden" style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%)">
                <div class="absolute top-4 right-4 px-2.5 py-0.5 rounded-full bg-white/20 text-white text-xs font-bold">?? Most Popular</div>
                <p class="text-sm font-bold text-indigo-200 uppercase tracking-wider mb-1">Lifetime Engine</p>
                <p class="text-4xl font-black mb-1">$79</p>
                <p class="text-sm text-indigo-200 mb-6">One-time payment. Forever.</p>
                <hr class="border-white/20 mb-6">
                @foreach(['Unlimited subscribers','Unlimited SMTP rotation','Turbo parallel delivery 100k/hr','IMAP Bounce Shield','API & Webhooks','Lifetime silent OTA updates','Full data sovereignty'] as $f)
                <div class="flex items-center gap-2 mb-3 text-sm text-indigo-100">
                    <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    {{ $f }}
                </div>
                @endforeach
                <a href="{{ route('register') }}" class="mt-6 block text-center py-3 px-6 rounded-xl bg-white text-indigo-700 text-sm font-black hover:bg-indigo-50 transition-colors shadow-lg">
                    Secure Lifetime Access — $79
                </a>
            </div>

            {{-- Pro --}}
            <div class="relative rounded-3xl p-8 bg-white dark:bg-[#080810] border border-slate-200 dark:border-white/5">
                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Pro Engine</p>
                <p class="text-4xl font-black text-slate-900 dark:text-white mb-1">$29<span class="text-lg font-medium text-slate-400">/yr</span></p>
                <p class="text-sm text-slate-500 mb-6">Scaled sending</p>
                <hr class="border-slate-200 dark:border-white/10 mb-6">
                @foreach(['Unlimited SMTP rotation','Turbo sending engine','IMAP Bounce Shield','API & Webhooks','Refer & Earn affiliate'] as $f)
                <div class="flex items-center gap-2 mb-3 text-sm text-slate-600 dark:text-slate-300">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    {{ $f }}
                </div>
                @endforeach
                <a href="{{ route('register') }}" class="mt-6 block text-center py-3 px-6 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 text-sm font-semibold hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors">
                    Get Pro Access
                </a>
            </div>
        </div>
    </div>
</section>

{{-- --------------------------- FAQ --------------------------- --}}
<section id="faq" class="py-20 bg-slate-50 dark:bg-[#080810]" x-data="{ open: null }">
    <div class="max-w-3xl mx-auto px-6">
        <h2 class="text-3xl font-black text-slate-900 dark:text-white text-center mb-10">Frequently Asked Questions</h2>
        @foreach([
            ['q'=>'Does this really work without any monthly subscription?','a'=>'Yes. You pay once and own the software permanently. You connect your own SMTP accounts (Amazon SES, SendGrid, Gmail, etc.) — those providers may have their own fees, but EliteSender itself has zero recurring cost.'],
            ['q'=>'How does the 100,000 emails per hour work?','a'=>'EliteSender uses a parallel delivery engine with intelligent micro-batching. By running multiple concurrent worker threads and spreading sends across your connected SMTP accounts, it can sustain extremely high throughput without burning a single IP.'],
            ['q'=>'What happens to my data?','a'=>'Your data stays entirely on your own server. EliteSender uses a local SQLite database with automatic WAL tuning. You can export your full contact list and campaign data as JSON at any time. No third-party has access.'],
            ['q'=>'Is there a money-back guarantee?','a'=>'Absolutely. If you install EliteSender, send your first campaign, and are not completely impressed within 14 days, request a full refund. Zero risk.'],
        ] as $i => $item)
        <div class="card mb-3 overflow-hidden">
            <button @click="open = open === {{ $i }} ? null : {{ $i }}"
                    class="w-full flex items-center justify-between gap-4 p-6 text-left">
                <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $item['q'] }}</span>
                <svg class="w-5 h-5 text-slate-400 flex-shrink-0 transition-transform duration-200"
                     :class="open === {{ $i }} ? 'rotate-180' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open === {{ $i }}"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="px-6 pb-6 text-sm text-slate-500 dark:text-slate-400 leading-relaxed border-t border-slate-100 dark:border-white/[0.05] pt-4">
                {{ $item['a'] }}
            </div>
        </div>
        @endforeach
    </div>
</section>

{{-- --------------------------- FINAL CTA --------------------------- --}}
<section class="py-24 relative overflow-hidden" style="background:linear-gradient(135deg,#0a0020 0%,#0d0030 50%,#050020 100%)">
    <div class="absolute inset-0 grid-overlay opacity-30"></div>
    <div class="absolute inset-0 mesh-bg"></div>
    <div class="relative z-10 max-w-3xl mx-auto px-6 text-center">
        <h2 class="text-4xl font-black text-white mb-5">Start Running Your Own Email Infrastructure Today</h2>
        <p class="text-lg text-slate-400 mb-10">Stop paying expensive subscriptions for tools you do not control. Take command of your delivery engine.</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('register') }}" class="btn-gradient py-4 px-8 text-base font-black">
                Secure Lifetime Access — $79
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
            <a href="{{ route('register') }}" class="px-8 py-4 text-sm font-semibold text-slate-400 border border-white/20 rounded-xl hover:border-white/40 hover:text-white transition-all">
                Try Free Version
            </a>
        </div>
        <p class="mt-6 text-sm text-slate-600">14-day money-back guarantee · No credit card required</p>
    </div>
</section>

{{-- Footer --}}
<footer class="py-12 border-t border-slate-200 dark:border-white/[0.05] bg-white dark:bg-[#050508]">
    <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background:linear-gradient(135deg,#4f46e5,#7c3aed)">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <span class="text-sm font-bold text-slate-900 dark:text-white">EliteSender</span>
        </div>
        <p class="text-sm text-slate-400">© {{ date('Y') }} EliteSender. All rights reserved.</p>
        <div class="flex items-center gap-4 text-sm text-slate-400">
            <a href="#" @click.prevent="window.$toast('Privacy Policy coming soon')" class="hover:text-slate-600 dark:hover:text-slate-200">Privacy</a>
            <a href="#" @click.prevent="window.$toast('Terms of Service coming soon')" class="hover:text-slate-600 dark:hover:text-slate-200">Terms</a>
        </div>
    </div>
</footer>

<x-toast />
</body>
</html>














