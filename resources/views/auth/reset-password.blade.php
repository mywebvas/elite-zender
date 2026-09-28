<x-layouts.guest header="Choose a new password"
                 subheader="Pick something long — length beats complexity">

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="input-label">Email address</label>
            <input id="email" name="email" type="email" autocomplete="email" required
                   value="{{ old('email', $request->email) }}"
                   class="input @error('email') ring-rose-400 dark:ring-rose-500 @enderror">
            @error('email')
                <p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="input-label">New password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required autofocus
                   class="input @error('password') ring-rose-400 dark:ring-rose-500 @enderror"
                   placeholder="••••••••••••">
            @error('password')
                <p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="input-label">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   autocomplete="new-password" required class="input">
        </div>

        <button type="submit" class="btn-gradient w-full justify-center" ::disabled="loading">
            <span x-show="!loading">Reset password</span>
            <span x-show="loading" x-cloak>Saving…</span>
        </button>
    </form>
</x-layouts.guest>
