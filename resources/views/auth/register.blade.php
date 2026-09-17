<x-layouts.guest header="Create your account">
    <form method="POST" action="{{ route('register') }}" class="space-y-6">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-100">Full name</label>
            <div class="mt-2">
                <input id="name" name="name" type="text" autocomplete="name" required value="{{ old('name') }}"
                       class="block w-full rounded-lg border-0 py-1.5 text-slate-900 dark:text-slate-100 dark:bg-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
            </div>
            @error('name')
                <p class="mt-2 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-100">Email address</label>
            <div class="mt-2">
                <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}"
                       class="block w-full rounded-lg border-0 py-1.5 text-slate-900 dark:text-slate-100 dark:bg-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
            </div>
            @error('email')
                <p class="mt-2 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-100">Password</label>
            <div class="mt-2">
                <input id="password" name="password" type="password" autocomplete="new-password" required
                       class="block w-full rounded-lg border-0 py-1.5 text-slate-900 dark:text-slate-100 dark:bg-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
            </div>
            @error('password')
                <p class="mt-2 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>
        
        <div>
            <label for="password_confirmation" class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-100">Confirm Password</label>
            <div class="mt-2">
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                       class="block w-full rounded-lg border-0 py-1.5 text-slate-900 dark:text-slate-100 dark:bg-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
            </div>
        </div>

        <div>
            <x-button type="submit" variant="primary" size="lg" class="w-full justify-center">
                Sign up
            </x-button>
        </div>
    </form>

    <p class="mt-10 text-center text-sm text-slate-500">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold leading-6 text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Sign in here</a>
    </p>
</x-layouts.guest>
