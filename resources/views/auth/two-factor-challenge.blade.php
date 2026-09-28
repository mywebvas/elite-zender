{{--
    Two-factor challenge.

    Fortify's twoFactorAuthentication() feature was enabled with no view bound,
    so this page threw a BindingResolutionException: anybody who turned 2FA on
    was permanently locked out of their own workspace.
--}}
<x-layouts.guest header="Two-factor authentication"
                 subheader="Confirm it's you to finish signing in">

    <div x-data="{ recovery: false }">
        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-5">
            @csrf

            <div x-show="!recovery">
                <label for="code" class="input-label">Authentication code</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       autofocus x-ref="code"
                       class="input tracking-[0.4em] text-center text-lg @error('code') ring-rose-400 dark:ring-rose-500 @enderror"
                       placeholder="000000">
                @error('code')
                    <p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Open your authenticator app and enter the six-digit code.
                </p>
            </div>

            <div x-show="recovery" x-cloak>
                <label for="recovery_code" class="input-label">Recovery code</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                       x-ref="recovery_code"
                       class="input @error('recovery_code') ring-rose-400 dark:ring-rose-500 @enderror"
                       placeholder="xxxxxxxx-xxxxxxxx">
                @error('recovery_code')
                    <p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Each recovery code can be used once.
                </p>
            </div>

            <button type="submit" class="btn-gradient w-full justify-center">Verify and continue</button>
        </form>

        <button type="button"
                @click="recovery = !recovery; $nextTick(() => (recovery ? $refs.recovery_code : $refs.code).focus())"
                class="mt-5 w-full text-center text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">
            <span x-show="!recovery">Lost your device? Use a recovery code</span>
            <span x-show="recovery" x-cloak>Use an authentication code instead</span>
        </button>
    </div>
</x-layouts.guest>
