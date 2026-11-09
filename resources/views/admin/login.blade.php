<!DOCTYPE html>
<html lang="en" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Operator Sign-in · {{ config('platform.name') }} Operations</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center bg-[#07070c] p-4 sm:p-6 font-sans text-slate-100 antialiased selection:bg-amber-500/30 selection:text-amber-200">

    <div class="fixed inset-0 pointer-events-none flex items-center justify-center overflow-hidden">
        <div class="h-[500px] w-[500px] rounded-full bg-amber-500/[0.04] blur-3xl"></div>
    </div>

    <main class="relative w-full max-w-sm">
        {{-- Brand Header --}}
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 via-amber-500 to-amber-600 shadow-[0_0_24px_-4px_rgba(245,158,11,0.6)]">
                <svg class="h-6 w-6 text-slate-950 font-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white">{{ config('platform.name') }}</h1>
            <div class="mt-1 flex items-center justify-center gap-1.5">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-amber-400">Operator Console</p>
            </div>
            <p class="mt-2 text-xs text-slate-400">Restricted surface for platform operations and security staff.</p>
        </div>

        {{-- Card Container --}}
        <div class="rounded-3xl border border-white/[0.08] bg-[#0c0c16]/90 p-7 shadow-2xl backdrop-blur-xl"
             x-data="{ showPass: false, loading: false }">

            @if ($errors->any())
                <div class="mb-5 rounded-2xl border border-rose-500/30 bg-rose-500/10 p-3.5 text-xs text-rose-300 shadow-sm" role="alert">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 flex-shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="font-medium">{{ $errors->first() }}</p>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}" @submit="loading = true" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-400">Email Address</label>
                    <input id="email" name="email" type="email" required autofocus autocomplete="username"
                           value="{{ old('email') }}"
                           placeholder="operator@elitesender.app"
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-amber-500 focus:bg-white/[0.06] transition">
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-400">Password</label>
                    <div class="relative">
                        <input id="password" name="password" :type="showPass ? 'text' : 'password'" required autocomplete="current-password"
                               placeholder="••••••••••••"
                               class="w-full rounded-xl border-0 bg-white/[0.04] pl-3.5 pr-10 py-2.5 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-amber-500 focus:bg-white/[0.06] transition">
                        <button type="button" @click="showPass = !showPass"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 p-1">
                            <svg x-show="!showPass" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showPass" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 select-none cursor-pointer text-xs text-slate-400">
                        <input type="checkbox" name="remember" value="1"
                               class="h-4 w-4 rounded-md border-white/20 bg-white/5 text-amber-500 focus:ring-amber-500 focus:ring-offset-0">
                        <span>Remember device</span>
                    </label>
                </div>

                <button type="submit"
                        :disabled="loading"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-3 text-sm font-bold text-slate-950 transition hover:bg-amber-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0c0c16] shadow-lg shadow-amber-950/40 active:scale-[0.99] disabled:opacity-50">
                    <svg x-show="loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span x-show="!loading">Authenticate to Console</span>
                    <span x-show="loading">Verifying credentials…</span>
                </button>
            </form>
        </div>

        {{-- Footer --}}
        <div class="mt-6 text-center text-xs text-slate-500">
            <p>New operator accounts are provisioned via CLI:</p>
            <code class="mt-1 inline-block rounded-lg bg-white/[0.04] px-2.5 py-1 font-mono text-[11px] text-amber-300 border border-white/[0.06]">php artisan elitesender:make-admin</code>
        </div>
    </main>
</body>
</html>
