<x-layouts.app header="Settings">
    <div class="max-w-7xl mx-auto" x-data="{
        activeTab: window.location.hash ? window.location.hash.substring(1) : 'general',
        deleteModalOpen: false,
        setTab(tab) {
            this.activeTab = tab;
            if (history.pushState) {
                history.pushState(null, null, '#' + tab);
            } else {
                location.hash = '#' + tab;
            }
        }
    }">
        @if(session('success'))
            <div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-sm text-emerald-700 dark:text-emerald-400 flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="lg:grid lg:grid-cols-12 lg:gap-x-8">
            {{-- Navigation Sidebar --}}
            <aside class="py-3 lg:py-6 lg:col-span-3">
                <nav class="flex lg:flex-col overflow-x-auto gap-1 pb-2 lg:pb-0">
                    <!-- General -->
                    <button type="button" @click="setTab('general')"
                            :class="activeTab === 'general' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-semibold shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'"
                            class="group flex items-center px-3.5 py-2 text-xs sm:text-sm font-medium rounded-xl flex-shrink-0 whitespace-nowrap lg:w-full text-left transition-colors">
                        <svg :class="activeTab === 'general' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300'"
                             class="flex-shrink-0 -ml-1 mr-2.5 h-4 w-4 sm:h-5 sm:w-5 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="truncate">General</span>
                    </button>

                    <!-- Security -->
                    <button type="button" @click="setTab('security')"
                            :class="activeTab === 'security' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-semibold shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'"
                            class="group flex items-center px-3.5 py-2 text-xs sm:text-sm font-medium rounded-xl flex-shrink-0 whitespace-nowrap lg:w-full text-left transition-colors">
                        <svg :class="activeTab === 'security' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300'"
                             class="flex-shrink-0 -ml-1 mr-2.5 h-4 w-4 sm:h-5 sm:w-5 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <span class="truncate">Security & 2FA</span>
                    </button>

                    <!-- Bounce Shield -->
                    <button type="button" @click="setTab('bounce')"
                            :class="activeTab === 'bounce' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-semibold shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'"
                            class="group flex items-center px-3.5 py-2 text-xs sm:text-sm font-medium rounded-xl flex-shrink-0 whitespace-nowrap lg:w-full text-left transition-colors">
                        <svg :class="activeTab === 'bounce' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300'"
                             class="flex-shrink-0 -ml-1 mr-2.5 h-4 w-4 sm:h-5 sm:w-5 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span class="truncate">Bounce Handling</span>
                    </button>

                    <!-- Email Preferences -->
                    <button type="button" @click="setTab('notifications')"
                            :class="activeTab === 'notifications' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-semibold shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'"
                            class="group flex items-center px-3.5 py-2 text-xs sm:text-sm font-medium rounded-xl flex-shrink-0 whitespace-nowrap lg:w-full text-left transition-colors">
                        <svg :class="activeTab === 'notifications' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300'"
                             class="flex-shrink-0 -ml-1 mr-2.5 h-4 w-4 sm:h-5 sm:w-5 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <span class="truncate">Email Preferences</span>
                    </button>

                    <!-- Data & Privacy (GDPR) -->
                    <button type="button" @click="setTab('data')"
                            :class="activeTab === 'data' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-semibold shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'"
                            class="group flex items-center px-3.5 py-2 text-xs sm:text-sm font-medium rounded-xl flex-shrink-0 whitespace-nowrap lg:w-full text-left transition-colors">
                        <svg :class="activeTab === 'data' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300'"
                             class="flex-shrink-0 -ml-1 mr-2.5 h-4 w-4 sm:h-5 sm:w-5 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        <span class="truncate">Data & Privacy (GDPR)</span>
                    </button>

                    <!-- Tracking Domains -->
                    <button type="button" @click="setTab('tracking')"
                            :class="activeTab === 'tracking' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-semibold shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'"
                            class="group flex items-center px-3.5 py-2 text-xs sm:text-sm font-medium rounded-xl flex-shrink-0 whitespace-nowrap lg:w-full text-left transition-colors">
                        <svg :class="activeTab === 'tracking' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300'"
                             class="flex-shrink-0 -ml-1 mr-2.5 h-4 w-4 sm:h-5 sm:w-5 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        <span class="truncate">Tracking Domains</span>
                    </button>

                    <!-- API Keys -->
                    <button type="button" @click="setTab('api')"
                            :class="activeTab === 'api' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 font-semibold shadow-xs' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'"
                            class="group flex items-center px-3.5 py-2 text-xs sm:text-sm font-medium rounded-xl flex-shrink-0 whitespace-nowrap lg:w-full text-left transition-colors">
                        <svg :class="activeTab === 'api' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300'"
                             class="flex-shrink-0 -ml-1 mr-2.5 h-4 w-4 sm:h-5 sm:w-5 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                        <span class="truncate">API Keys</span>
                    </button>
                </nav>
            </aside>

            {{-- Main Content Column - Wraps ALL Tabs Cleanly --}}
            <div class="space-y-6 sm:px-6 lg:px-0 lg:col-span-9">

                <!-- 1. General Tab -->
                <div x-show="activeTab === 'general'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card>
                        <div class="p-6 border-b border-slate-200 dark:border-white/10">
                            <h2 class="text-lg leading-6 font-semibold text-slate-900 dark:text-white">Workspace Details</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Update your workspace identity, operating timezone, and billing details.</p>
                        </div>
                        <div class="p-6">
                            <form method="POST" action="{{ route('settings.update') }}" x-data="{ saving: false }" @submit="if(saving){ $event.preventDefault(); return false; } saving = true;" class="space-y-6">
                                @csrf
                                @method('PUT')

                                <div>
                                    <label for="workspace_name" class="input-label">Workspace name</label>
                                    {{-- Pre-filled with workspace name, never user's personal name --}}
                                    <input id="workspace_name" type="text" name="workspace_name"
                                           value="{{ old('workspace_name', $tenant?->name) }}" class="input @error('workspace_name') ring-rose-400 @enderror">
                                    @error('workspace_name')<p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="timezone" class="input-label">Timezone</label>
                                    <select id="timezone" name="timezone" class="input">
                                        @foreach([
                                            'UTC' => 'UTC (Coordinated Universal Time)',
                                            'Africa/Lagos' => 'Africa/Lagos',
                                            'Africa/Accra' => 'Africa/Accra',
                                            'Africa/Nairobi' => 'Africa/Nairobi',
                                            'Africa/Johannesburg' => 'Africa/Johannesburg',
                                            'Europe/London' => 'Europe/London',
                                            'Europe/Paris' => 'Europe/Paris',
                                            'America/New_York' => 'America/New_York',
                                            'America/Chicago' => 'America/Chicago',
                                            'America/Denver' => 'America/Denver',
                                            'America/Los_Angeles' => 'America/Los_Angeles',
                                            'Asia/Dubai' => 'Asia/Dubai',
                                            'Asia/Kolkata' => 'Asia/Kolkata',
                                            'Australia/Sydney' => 'Australia/Sydney',
                                        ] as $tz => $label)
                                            <option value="{{ $tz }}" @selected(old('timezone', $tenant?->timezone()) === $tz)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Used for campaign scheduling and midnight quota resets.</p>
                                </div>

                                <div>
                                    <label for="country" class="input-label">Billing country</label>
                                    <select id="country" name="country" class="input @error('country') ring-rose-400 @enderror">
                                        <option value="">Choose your country…</option>
                                        @foreach(App\Support\Countries::all() as $code => $name)
                                            <option value="{{ $code }}" @selected(old('country', $tenant?->setting('country')) === $code)>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                                        Determines the currency you are invoiced in.
                                        @php $billingCurrency = $tenant ? app(App\Billing\BillingService::class)->currencyFor($tenant) : null; @endphp
                                        @if($billingCurrency)
                                            You are currently billed in <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $billingCurrency }}</span>.
                                        @endif
                                    </p>
                                    @error('country')<p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                                </div>

                                <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-white/[0.06]">
                                    <x-button type="submit" variant="primary" x-bind:disabled="saving">
                                        <svg x-show="saving" class="w-4 h-4 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        <span x-show="!saving">Save Changes</span><span x-show="saving">Saving…</span>
                                    </x-button>
                                </div>
                            </form>
                        </div>
                    </x-card>
                </div>

                <!-- 2. Security Tab -->
                <div x-show="activeTab === 'security'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-6">
                    <x-card>
                        <div class="p-6 border-b border-slate-200 dark:border-white/10">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Two-Factor Authentication</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                Secure your account using standard TOTP authenticator apps (1Password, Google Authenticator, Authy).
                            </p>
                        </div>
                        <div class="p-6">
                            <form action="/user/two-factor-authentication" method="POST">
                                @csrf
                                @if(auth()->user()->hasEnabledTwoFactorAuthentication())
                                    @method('DELETE')
                                    <x-button type="submit" variant="danger">Disable 2FA</x-button>
                                @else
                                    <x-button type="submit" variant="primary">Enable 2FA</x-button>
                                @endif
                            </form>

                            @if(session('status') == 'two-factor-authentication-enabled')
                                <div class="mt-4 p-4 rounded-xl bg-indigo-50 border border-indigo-100 dark:bg-indigo-900/30 dark:border-indigo-800/50">
                                    <p class="text-sm font-medium text-indigo-800 dark:text-indigo-300 mb-3">Two-factor authentication is now enabled. Scan the following QR code using your phone's authenticator application.</p>
                                    <div class="bg-white p-2 inline-block rounded-lg shadow-sm">
                                        {!! auth()->user()->twoFactorQrCodeSvg() !!}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </x-card>

                    <x-card>
                        <div class="p-6 border-b border-slate-200 dark:border-white/10">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Security Audit Log</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                Review recent security events and session origins to protect against unauthorized access.
                            </p>
                        </div>
                        <div class="p-0 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-white/5">
                                <thead class="bg-slate-50/50 dark:bg-white/[0.02]">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Event</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">IP Address</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-200 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                            Active Session Authenticated
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-slate-500 dark:text-slate-400">{{ request()->ip() }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">Current session</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>

                <!-- 3. Bounce Shield Tab -->
                <div x-show="activeTab === 'bounce'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card>
                        <div class="p-6 border-b border-slate-200 dark:border-white/10">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Bounce Shield (Auto-Cleaner)</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Configure an IMAP inbox to automatically scan bounce notices, analyze error codes, and suppress dead addresses every 15 minutes.</p>
                        </div>
                        <form method="POST" action="{{ route('settings.imap') }}" x-data="{ saving: false }" @submit="if(saving){ $event.preventDefault(); return false; } saving = true;" class="p-6 space-y-6">
                            @csrf
                            @method('PUT')

                            @php $imap = $tenant?->setting('imap') ?? []; @endphp

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="imap_host" class="input-label">IMAP host</label>
                                    <input id="imap_host" name="host" type="text" class="input @error('host') ring-rose-400 @enderror"
                                           value="{{ old('host', $imap['host'] ?? '') }}" placeholder="imap.gmail.com">
                                    @error('host')<p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="imap_port" class="input-label">Port</label>
                                    <input id="imap_port" name="port" type="number" class="input @error('port') ring-rose-400 @enderror"
                                           value="{{ old('port', $imap['port'] ?? 993) }}" placeholder="993">
                                    @error('port')<p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="imap_username" class="input-label">Mailbox address</label>
                                    <input id="imap_username" name="username" type="text" class="input @error('username') ring-rose-400 @enderror"
                                           value="{{ old('username', $imap['username'] ?? '') }}" placeholder="bounces@yourdomain.com">
                                    @error('username')<p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="imap_password" class="input-label">
                                        Password / app password
                                        @if(filled($imap['password'] ?? null))
                                            <span class="font-normal text-slate-400">— leave blank to keep unchanged</span>
                                        @endif
                                    </label>
                                    <input id="imap_password" name="password" type="password" autocomplete="new-password"
                                           class="input @error('password') ring-rose-400 @enderror"
                                           placeholder="{{ filled($imap['password'] ?? null) ? '••••••••' : 'App password' }}">
                                    @error('password')<p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="imap_encryption" class="input-label">Encryption</label>
                                    <select id="imap_encryption" name="encryption" class="input">
                                        <option value="ssl" @selected(old('encryption', $imap['encryption'] ?? 'ssl') === 'ssl')>SSL / TLS (993)</option>
                                        <option value="none" @selected(old('encryption', $imap['encryption'] ?? 'ssl') === 'none')>None (143)</option>
                                    </select>
                                </div>
                            </div>

                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Point this at the mailbox your relays deliver bounces to. We read it every 15 minutes,
                                classify hard bounces and complaints, and suppress those addresses automatically.
                                The password is encrypted at rest using AES-256.
                            </p>

                            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-white/[0.06]">
                                @if(filled($imap['host'] ?? null))
                                    <button type="submit" name="disconnect" value="1"
                                            class="rounded-xl px-4 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                                        Disconnect
                                    </button>
                                @endif
                                <x-button type="submit" variant="primary" x-bind:disabled="saving">
                                    <svg x-show="saving" class="w-4 h-4 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <span x-show="!saving">Save configuration</span><span x-show="saving">Saving…</span>
                                </x-button>
                            </div>
                        </form>
                    </x-card>
                </div>

                <!-- 4. Email Preferences Tab -->
                <div x-show="activeTab === 'notifications'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card>
                        <div class="border-b border-slate-200 p-6 dark:border-white/10">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Email Preferences</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Control which lifecycle and report digests you receive. Critical security alerts and billing invoices are always active.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('settings.notifications') }}" x-data="{ saving: false }" @submit="if(saving){ $event.preventDefault(); return false; } saving = true;" class="space-y-6 p-6">
                            @csrf
                            @method('PUT')

                            <div class="space-y-4">
                                @foreach(App\Models\User::OPTIONAL_NOTIFICATIONS as $key => $meta)
                                    <label class="flex cursor-pointer items-start gap-3.5 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <input type="checkbox" name="{{ $key }}" value="1"
                                               @checked(auth()->user()->wantsNotification($key))
                                               class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20 dark:bg-white/5">
                                        <span>
                                            <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $meta['label'] }}</span>
                                            <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ $meta['help'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-300 flex items-start gap-2.5">
                                <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span><strong class="font-semibold text-slate-900 dark:text-white">Always on:</strong> Invoices, payment receipts, sending quota threshold alerts, and critical security notices (password resets, 2FA modifications, seat revokations).</span>
                            </div>

                            <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-white/[0.06]">
                                <x-button type="submit" variant="primary" x-bind:disabled="saving">
                                    <svg x-show="saving" class="w-4 h-4 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <span x-show="!saving">Save preferences</span><span x-show="saving">Saving…</span>
                                </x-button>
                            </div>
                        </form>
                    </x-card>
                </div>

                <!-- 5. Data & Privacy (GDPR) Tab -->
                <div x-show="activeTab === 'data'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-6">
                    <x-card id="data">
                        <div class="border-b border-slate-200 p-6 dark:border-white/10">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Your Data & GDPR Rights</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Full data portability (Art. 15) and erasure rights (Art. 17). Fully automated without manual tickets.
                            </p>
                        </div>

                        <div class="space-y-6 p-6">
                            {{-- Export Section --}}
                            <div>
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Export workspace archive</p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                            Generates a complete ZIP containing all contacts, subscriber activity, campaigns, relay metrics, and team rosters in standard CSV format. Credentials and secrets are safely omitted.
                                        </p>
                                    </div>
                                    <form method="POST" action="{{ route('data.export') }}" class="flex-shrink-0" x-data="{ exporting: false }" @submit="if(exporting){ $event.preventDefault(); return false; } exporting = true;">
                                        @csrf
                                        <x-button type="submit" variant="secondary" x-bind:disabled="exporting">
                                            <svg x-show="exporting" class="w-4 h-4 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            <span x-show="!exporting">Request export</span><span x-show="exporting">Requesting…</span>
                                        </x-button>
                                    </form>
                                </div>

                                @if($exports->isNotEmpty())
                                    <ul class="mt-5 space-y-2.5">
                                        @foreach($exports as $export)
                                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 dark:border-white/10 p-3.5 text-sm bg-slate-50/50 dark:bg-white/[0.02]">
                                                <div class="flex items-center gap-2.5 text-slate-600 dark:text-slate-300">
                                                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                    <span>{{ $export->created_at?->toFormattedDayDateString() }}</span>
                                                    @if($export->status === App\Models\DataRequest::STATUS_PENDING)
                                                        · <span class="inline-flex items-center gap-1 font-medium text-amber-600 dark:text-amber-400">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> preparing…
                                                        </span>
                                                    @elseif($export->isDownloadable())
                                                        · <span class="text-slate-500 font-mono text-xs">{{ number_format(($export->file_size ?? 0) / 1024) }} KB</span>
                                                        · <span class="text-xs text-slate-400">expires {{ $export->expires_at?->diffForHumans() }}</span>
                                                    @elseif($export->status === App\Models\DataRequest::STATUS_FAILED)
                                                        · <span class="font-medium text-rose-600 dark:text-rose-400">generation failed</span>
                                                    @else
                                                        · <span class="text-slate-400">expired</span>
                                                    @endif
                                                </div>
                                                @if($export->isDownloadable())
                                                    <a href="{{ route('data.download', $export) }}"
                                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-brand-50 text-brand-700 hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-300 transition-colors">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                        Download Archive
                                                    </a>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            {{-- Workspace Erasure Section --}}
                            <div class="border-t border-slate-100 pt-6 dark:border-white/[0.06]">
                                @if($pendingDeletion)
                                    <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-5 dark:border-rose-500/20 dark:bg-rose-500/10">
                                        <div class="flex items-start gap-3">
                                            <div class="p-2 rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400 shrink-0">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm font-semibold text-rose-900 dark:text-rose-200">
                                                    Workspace scheduled for permanent deletion on {{ $pendingDeletion->scheduled_for?->toFormattedDayDateString() }}
                                                </p>
                                                <p class="mt-1 text-xs text-rose-800 dark:text-rose-300">
                                                    During the {{ App\Models\DataRequest::DELETION_GRACE_DAYS }}-day grace period, all services remain active. You can cancel at any point prior to the deadline.
                                                </p>
                                                <form method="POST" action="{{ route('data.delete.cancel', $pendingDeletion) }}" class="mt-3.5"
                                                      x-data="{ cancelling: false }" @submit="if(cancelling){ $event.preventDefault(); return false; } cancelling = true;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-button type="submit" variant="primary" ::disabled="cancelling">
                                                        <span x-show="!cancelling">Cancel Deletion & Keep Workspace</span>
                                                        <span x-show="cancelling" x-cloak>Cancelling…</span>
                                                    </x-button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @elseif(auth()->user()->isOwner())
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Delete this workspace</p>
                                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                                Permanently deletes all contacts, lists, relays, templates, and team access. A {{ App\Models\DataRequest::DELETION_GRACE_DAYS }}-day safety grace period applies before irreversible purging.
                                            </p>
                                        </div>
                                        <button type="button" @click="deleteModalOpen = true"
                                                class="flex-shrink-0 rounded-xl px-4 py-2 text-sm font-semibold text-rose-600 ring-1 ring-inset ring-rose-200 transition hover:bg-rose-50 dark:text-rose-400 dark:ring-rose-500/30 dark:hover:bg-rose-500/10">
                                            Delete workspace…
                                        </button>
                                    </div>

                                    {{-- Deletion Confirmation Modal --}}
                                    <x-modal x-show="deleteModalOpen" @close-modal="deleteModalOpen = false" title="Delete Workspace Permanently" size="md">
                                        <form method="POST" action="{{ route('data.delete') }}" x-data="{ deleting: false }" @submit="if(deleting){ $event.preventDefault(); return false; } deleting = true;" class="space-y-4">
                                            @csrf

                                            <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-xs text-rose-700 dark:text-rose-300">
                                                This action initiates a {{ App\Models\DataRequest::DELETION_GRACE_DAYS }}-day countdown. After that date, all data is permanently purged and unrecoverable.
                                            </div>

                                            <div>
                                                <label for="confirmation" class="input-label">
                                                    Type <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $tenant?->name }}</span> to confirm
                                                </label>
                                                <input id="confirmation" name="confirmation" type="text" autocomplete="off"
                                                       class="input @error('confirmation') ring-rose-400 @enderror" placeholder="{{ $tenant?->name }}" required>
                                                @error('confirmation')<p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                                            </div>

                                            <div>
                                                <label for="delete-password" class="input-label">Your password</label>
                                                <input id="delete-password" name="password" type="password" autocomplete="current-password"
                                                       class="input @error('password') ring-rose-400 @enderror" placeholder="••••••••" required>
                                                @error('password')<p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                                            </div>

                                            <div>
                                                <label for="delete-reason" class="input-label">Why are you leaving? <span class="font-normal text-slate-400">(optional)</span></label>
                                                <select id="delete-reason" name="reason" class="input">
                                                    <option value="">Prefer not to say</option>
                                                    @foreach(App\Models\Subscription::CANCELLATION_REASONS as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="flex justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-white/[0.06]">
                                                <x-button type="button" variant="secondary" @click="deleteModalOpen = false">Keep my workspace</x-button>
                                                <x-button type="submit" variant="danger" x-bind:disabled="deleting">
                                                    <svg x-show="deleting" class="w-4 h-4 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                    <span x-show="!deleting">Schedule deletion</span><span x-show="deleting">Scheduling…</span>
                                                </x-button>
                                            </div>
                                        </form>
                                    </x-modal>
                                @else
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        Only the workspace owner can schedule workspace deletion.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </x-card>
                </div>

                <!-- 6. Tracking Tab -->
                <div x-show="activeTab === 'tracking'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card>
                        <div class="p-6 border-b border-slate-200 dark:border-white/10 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Custom Tracking Domains</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Boost deliverability by isolating click and open tracking under your own white-labeled subdomain.</p>
                            </div>
                        </div>
                        <div class="p-10 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-slate-100 dark:bg-white/[0.04] text-slate-400 dark:text-slate-500 mb-4">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                            </div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-200">Default tracking active</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto">Using verified platform domains for high-reputation click redirects and 1x1 open beacons.</p>
                        </div>
                    </x-card>
                </div>

                <!-- 7. API Tab -->
                <div x-show="activeTab === 'api'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card>
                        <div class="p-6 border-b border-slate-200 dark:border-white/10 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">API Access & Webhooks</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Bearer tokens for programmatic contact synchronization and webhook dispatching.</p>
                            </div>
                        </div>
                        <div class="p-10 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-slate-100 dark:bg-white/[0.04] text-slate-400 dark:text-slate-500 mb-4">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                            </div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-200">REST API v1</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto">Access workspace endpoints with scoped tenant authorization keys.</p>
                        </div>
                    </x-card>
                </div>

            </div>
        </div>
    </div>
</x-layouts.app>
