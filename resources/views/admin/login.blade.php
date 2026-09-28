<!DOCTYPE html>
<html lang="en" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Operator sign-in · EliteSender</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex h-full items-center justify-center bg-slate-950 p-6 font-sans text-slate-100 antialiased">
    <main class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-500">
                <svg class="h-6 w-6 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h1 class="text-xl font-bold">Operator console</h1>
            <p class="mt-1 text-sm text-slate-400">Platform staff only.</p>
        </div>

        <div class="rounded-2xl border border-white/10 bg-[#0b0b14] p-6">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-rose-500/20 bg-rose-500/10 p-3" role="alert">
                    <p class="text-sm text-rose-300">{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="mb-1.5 block text-xs font-medium text-slate-400">Email</label>
                    <input id="email" name="email" type="email" required autofocus autocomplete="username"
                           value="{{ old('email') }}"
                           class="w-full rounded-lg border-0 bg-white/5 px-3 py-2.5 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-500 focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-xs font-medium text-slate-400">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="w-full rounded-lg border-0 bg-white/5 px-3 py-2.5 text-sm text-white ring-1 ring-white/10 focus:ring-2 focus:ring-amber-500">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-400">
                    <input type="checkbox" name="remember" value="1" class="rounded border-white/20 bg-white/5 text-amber-500 focus:ring-amber-500">
                    Remember this device
                </label>
                <button type="submit" class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                    Sign in
                </button>
            </form>
        </div>

        <p class="mt-4 text-center text-xs text-slate-500">
            Accounts are created on the server with <code class="text-slate-400">artisan elitesender:make-admin</code>.
        </p>
    </main>
</body>
</html>
