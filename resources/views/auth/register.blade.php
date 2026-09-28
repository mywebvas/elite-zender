<x-layouts.guest header="Create your account" subheader="Join 2,400+ marketers who own their email infrastructure">
    <form method="POST" action="{{ route('register') }}" class="space-y-5"
          x-data="{ loading: false, password: '', strength: 0, getStrength() {
              let s = 0;
              if(this.password.length >= 8) s++;
              if(/[A-Z]/.test(this.password)) s++;
              if(/[0-9]/.test(this.password)) s++;
              if(/[^A-Za-z0-9]/.test(this.password)) s++;
              this.strength = s;
          }}"
          @submit="loading = true">
        @csrf

        {{-- Name --}}
        <div>
            <label for="name" class="input-label">Full name</label>
            <input id="name" name="name" type="text" autocomplete="name" required
                   value="{{ old('name') }}"
                   class="input @error('name') ring-rose-400 @enderror"
                   placeholder="Alex Johnson">
            @error('name')
                <p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- Email --}}
        <div>
            <label for="email" class="input-label">Email address</label>
            <input id="email" name="email" type="email" autocomplete="email" required
                   value="{{ old('email') }}"
                   class="input @error('email') ring-rose-400 @enderror"
                   placeholder="alex@company.com">
            @error('email')
                <p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div>
            <label for="password" class="input-label">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required
                   x-model="password" @input="getStrength()"
                   class="input @error('password') ring-rose-400 @enderror"
                   placeholder="Min. 8 characters">
            {{-- Strength bar --}}
            <div class="mt-2 flex gap-1">
                <template x-for="i in 4" :key="i">
                    <div class="h-1 flex-1 rounded-full transition-all duration-300"
                         :class="{
                             'bg-rose-400': strength >= i && strength === 1,
                             'bg-amber-400': strength >= i && strength === 2,
                             'bg-yellow-400': strength >= i && strength === 3,
                             'bg-emerald-500': strength >= i && strength === 4,
                             'bg-slate-200 dark:bg-white/10': strength < i
                         }"></div>
                </template>
            </div>
            <p class="mt-1 text-xs text-slate-400" x-show="password.length > 0">
                <span x-text="['','Weak — add uppercase','Fair — add numbers','Good — add symbols','Strong password'][strength]"></span>
            </p>
            @error('password')
                <p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- Confirm password --}}
        <div>
            <label for="password_confirmation" class="input-label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                   class="input"
                   placeholder="Repeat password">
        </div>

        {{-- Terms --}}
        <div class="flex items-start gap-3">
            <input type="checkbox" required id="terms"
                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <label for="terms" class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                I agree to the <a href="#" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Terms of Service</a>
                and <a href="#" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Privacy Policy</a>
            </label>
        </div>

        {{-- Submit --}}
        <button type="submit" :disabled="loading"
                class="w-full btn-gradient py-3 text-base font-bold rounded-xl transition-all">
            <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span x-show="!loading">Create free account</span>
            <span x-show="loading">Creating account...</span>
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline ml-1">Sign in</a>
    </p>
</x-layouts.guest>
