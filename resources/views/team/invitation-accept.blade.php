<x-layouts.guest :header="'Join '.$workspace"
                 subheader="Pick a password and you are in">

    <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}" class="space-y-5"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf

        {{-- The address is fixed by the invitation. Letting the invitee edit it
             would turn one leaked link into a way into any workspace under any
             identity. --}}
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Invited address</p>
            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $invitation->email }}</p>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                Joining <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $workspace }}</span>
                as {{ $invitation->role }}.
            </p>
        </div>

        <div>
            <label for="name" class="input-label">Your name</label>
            <input id="name" name="name" type="text" required autofocus autocomplete="name"
                   value="{{ old('name') }}"
                   class="input @error('name') ring-rose-400 dark:ring-rose-500 @enderror"
                   placeholder="Ada Lovelace">
            @error('name')<p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password" class="input-label">Choose a password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="input @error('password') ring-rose-400 dark:ring-rose-500 @enderror">
            @error('password')<p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="input-label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="input">
        </div>

        <button type="submit" :disabled="loading"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60">
            <span x-show="!loading">Join {{ $workspace }}</span>
            <span x-show="loading" x-cloak>Setting up…</span>
        </button>

        <p class="text-center text-xs text-slate-500 dark:text-slate-400">
            This link expires {{ $invitation->expires_at->diffForHumans() }}.
        </p>
    </form>

</x-layouts.guest>
