<x-layouts.guest header="Welcome back" subheader="Sign in to your email infrastructure">
    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ loading: false }" @submit="loading = true">
        @csrf

        {{-- Email --}}
        <div>
            <label for="email" class="input-label">Email address</label>
            <input id="email" name="email" type="email" autocomplete="email" required
                   value="{{ old('email') }}"
                   class="input @error('email') ring-rose-400 dark:ring-rose-500 @enderror"
                   placeholder="you@company.com">
            @error('email')
                <p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <label for="password" class="input-label mb-0">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Forgot password?</a>
            </div>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   class="input @error('password') ring-rose-400 dark:ring-rose-500 @enderror"
                   placeholder="••••••••">
            @error('password')
                <p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember + status --}}
        <div class="flex items-center gap-3">
            <input id="remember" name="remember" type="checkbox"
                   class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-white/5">
            <label for="remember" class="text-sm text-slate-600 dark:text-slate-400 select-none cursor-pointer">Keep me signed in</label>
        </div>

        @if (session('status'))
            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-sm text-emerald-700 dark:text-emerald-400">
                {{ session('status') }}
            </div>
        @endif

        {{-- Submit --}}
        <button type="submit" :disabled="loading"
                class="w-full btn-gradient py-3 text-base font-bold rounded-xl transition-all">
            <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span x-show="!loading">Sign in to EliteSender</span>
            <span x-show="loading">Signing in...</span>
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
        No account yet?
        <a href="{{ route('register') }}" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline ml-1">Start free — no credit card</a>
    </p>
</x-layouts.guest>
