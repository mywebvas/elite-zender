<x-layouts.app header="Settings">
    <div class="max-w-7xl mx-auto" x-data="{ activeTab: 'general' }">
                @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-sm text-emerald-700 dark:text-emerald-400 flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
        @endif<div class="lg:grid lg:grid-cols-12 lg:gap-x-8">
            <aside class="py-6 lg:col-span-3">
                <nav class="space-y-1">
                    <!-- General -->
                    <button @click="activeTab = 'general'" :class="activeTab === 'general' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md w-full text-left transition-colors">
                        <svg :class="activeTab === 'general' ? 'text-indigo-700 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-500 dark:group-hover:text-slate-300'" class="flex-shrink-0 -ml-1 mr-3 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <span class="truncate">General</span>
                    </button>

                    <!-- Security -->
                    <button @click="activeTab = 'security'" :class="activeTab === 'security' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md w-full text-left transition-colors">
                        <svg :class="activeTab === 'security' ? 'text-indigo-700 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-500 dark:group-hover:text-slate-300'" class="flex-shrink-0 -ml-1 mr-3 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <span class="truncate">Security & 2FA</span>
                    </button>
                    
                    <!-- Tracking -->
                    <button @click="activeTab = 'tracking'" :class="activeTab === 'tracking' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md w-full text-left transition-colors">
                        <svg :class="activeTab === 'tracking' ? 'text-indigo-700 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-500 dark:group-hover:text-slate-300'" class="flex-shrink-0 -ml-1 mr-3 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        <span class="truncate">Tracking Domains</span>
                    </button>

                    <!-- API Keys -->
                    <button @click="activeTab = 'api'" :class="activeTab === 'api' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md w-full text-left transition-colors">
                        <svg :class="activeTab === 'api' ? 'text-indigo-700 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-500 dark:group-hover:text-slate-300'" class="flex-shrink-0 -ml-1 mr-3 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                        <span class="truncate">API Keys</span>
                    </button>
                    <!-- Bounce Shield -->
                    <button @click="activeTab = 'bounce'" :class="activeTab === 'bounce' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5'" class="group flex items-center px-3 py-2 text-sm font-medium rounded-md w-full text-left transition-colors">
                        <svg :class="activeTab === 'bounce' ? 'text-indigo-700 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-500 dark:group-hover:text-slate-300'" class="flex-shrink-0 -ml-1 mr-3 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <span class="truncate">Bounce Shield</span>
                    </button>
                </nav>
            </aside>

            <div class="space-y-6 sm:px-6 lg:px-0 lg:col-span-9">
                <!-- General Tab -->
                <div x-show="activeTab === 'general'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card>
                        <div class="p-6 border-b border-slate-200 dark:border-white/10">
                            <h2 class="text-lg leading-6 font-medium text-slate-900 dark:text-white">Workspace Details</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Update your workspace name and branding.</p>
                        </div>
                        <div class="p-6 space-y-6">
                            <form method="POST" action="{{ route('settings.update') }}" x-data="{ saving: false }" @submit="saving = true">
                                @csrf
                            <div>
                                <label class="input-label">Workspace Name</label>
                                <input type="text" name="workspace_name" value="{{ auth()->user()->name ?? 'Default Workspace' }}" class="input">
                            </div>
                            <div>
                                <label class="input-label">Timezone</label>
                                <select name="timezone" class="input">
                                    <option value="UTC">UTC (Coordinated Universal Time)</option>
                                    <option value="America/New_York">America/New_York</option>
                                    <option value="America/Chicago">America/Chicago</option>
                                    <option value="America/Denver">America/Denver</option>
                                    <option value="America/Los_Angeles">America/Los_Angeles</option>
                                    <option value="Europe/London">Europe/London</option>
                                    <option value="Europe/Paris">Europe/Paris</option>
                                    <option value="Africa/Lagos">Africa/Lagos</option>
                                    <option value="Asia/Dubai">Asia/Dubai</option>
                                </select>
                            </div>
                            <div class="flex justify-end pt-4">
                                <x-button type="submit" variant="primary" x-bind:disabled="saving">
                                    <svg x-show="saving" class="w-4 h-4 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <span x-show="!saving">Save Changes</span><span x-show="saving">Saving...</span>
                                </x-button>
                            </div>
                            </form>
                        </div>
                    </x-card>
                </div>

                <!-- Security Tab (Original Content) -->
                <div x-show="activeTab === 'security'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card class="mb-6">
                        <div class="p-6 border-b border-slate-200 dark:border-white/10">
                            <h3 class="text-lg font-medium text-slate-900 dark:text-white">Two-Factor Authentication</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                Add additional security to your account using two-factor authentication (2FA).
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
                            <h3 class="text-lg font-medium text-slate-900 dark:text-white">Security Audit Log</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                Review recent security events on your account to ensure there is no unauthorized access.
                            </p>
                        </div>
                        <div class="p-0 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-white/5">
                                <thead class="bg-slate-50/50 dark:bg-white/[0.02]">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Event</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">IP Address</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-slate-300">Login Successful</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ request()->ip() }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">Just now</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>

                <!-- Tracking Tab -->
                <div x-show="activeTab === 'tracking'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card>
                        <div class="p-6 border-b border-slate-200 dark:border-white/10 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-medium text-slate-900 dark:text-white">Custom Tracking Domains</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Improve deliverability by tracking clicks and opens using your own domain.</p>
                            </div>
                            <x-button variant="secondary" @click="window.$toast('Custom tracking domains coming soon!', 'info')">Add Domain</x-button>
                        </div>
                        <div class="p-6 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 mb-4">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                            </div>
                            <h3 class="text-sm font-medium text-slate-900 dark:text-slate-200">No custom domains configured</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Using the default system domain for tracking.</p>
                        </div>
                    </x-card>
                </div>

                <!-- API Tab -->
                <div x-show="activeTab === 'api'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card>
                        <div class="p-6 border-b border-slate-200 dark:border-white/10 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-medium text-slate-900 dark:text-white">API Access</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage API keys for Zapier and webhook integrations.</p>
                            </div>
                            <x-button variant="secondary" @click="window.$toast('API key generation coming soon!', 'info')">Generate Key</x-button>
                        </div>
                        <div class="p-6 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 mb-4">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                            </div>
                            <h3 class="text-sm font-medium text-slate-900 dark:text-slate-200">No API keys found</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create a key to connect EliteSender with third-party tools.</p>
                        </div>
                    </x-card>
                </div>
            </div>
                <!-- Bounce Shield Tab -->
                <div x-show="activeTab === 'bounce'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card>
                        <div class="p-6 border-b border-slate-200 dark:border-white/10 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-medium text-slate-900 dark:text-white">Bounce Shield (Auto-Cleaner)</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Configure IMAP to automatically scan your inbox for bounced emails and suppress them.</p>
                            </div>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="input-label">IMAP Host</label>
                                    <input type="text" class="input" placeholder="imap.gmail.com">
                                </div>
                                <div>
                                    <label class="input-label">IMAP Port</label>
                                    <input type="number" class="input" placeholder="993">
                                </div>
                                <div>
                                    <label class="input-label">Email Address</label>
                                    <input type="email" class="input" placeholder="bounces@yourdomain.com">
                                </div>
                                <div>
                                    <label class="input-label">Password / App Password</label>
                                    <input type="password" class="input" placeholder="********">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <x-button @click="window.$toast('IMAP Settings saved successfully', 'success')" variant="primary">Save Configuration</x-button>
                            </div>
                        </div>
                    </x-card>
                </div>
        </div>
    </div>
</x-layouts.app>








