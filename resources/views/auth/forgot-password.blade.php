<x-layouts.guest header="Reset your password"
                 subheader="We'll email you a secure link to choose a new one">

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10"
             role="status">
            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ session('status') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf

        <div>
            <label for="email" class="input-label">Email address</label>
            <input id="email" name="email" type="email" autocomplete="email" required autofocus
                   value="{{ old('email') }}"
                   class="input @error('email') ring-rose-400 dark:ring-rose-500 @enderror"
                   placeholder="you@company.com">
            @error('email')
                <p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-gradient w-full justify-center" ::disabled="loading">
            <span x-show="!loading">Email password reset link</span>
            <span x-show="loading" x-cloak>Sending…</span>
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        Remembered it?
        <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Back to sign in</a>
    </p>
</x-layouts.guest>
