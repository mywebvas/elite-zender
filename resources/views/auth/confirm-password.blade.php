<x-layouts.guest header="Confirm your password"
                 subheader="This is a sensitive area — please confirm it's you">

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="password" class="input-label">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required autofocus
                   class="input @error('password') ring-rose-400 dark:ring-rose-500 @enderror"
                   placeholder="••••••••">
            @error('password')
                <p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-gradient w-full justify-center">Confirm</button>
    </form>
</x-layouts.guest>
