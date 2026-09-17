<x-layouts.app header="SMTP Pool">
    <div class="max-w-7xl mx-auto" x-data="smtpManager()">
        <div class="sm:flex sm:items-center sm:justify-between mb-8">
            <div>
                <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">Manage your sender accounts. The system automatically rotates through healthy accounts.</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <x-button variant="primary" @click="isAdding = true">Add Account</x-button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <template x-for="account in accounts" :key="account.id">
                <x-card class="flex flex-col relative">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate" x-text="account.name"></h3>
                            <x-badge :variant="account.status === 'active' ? 'success' : (account.status === 'error' ? 'danger' : 'slate')" x-text="account.status"></x-badge>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 truncate" x-text="account.host + ':' + account.port"></p>
                        
                        <div class="mt-6 flex items-end justify-between">
                            <div>
                                <p class="text-xs text-slate-500 mb-1">Health Score</p>
                                <div class="flex items-center gap-2">
                                    <div class="text-2xl font-semibold" :class="account.health >= 80 ? 'text-emerald-600 dark:text-emerald-400' : (account.health >= 50 ? 'text-amber-500' : 'text-rose-600 dark:text-rose-400')" x-text="account.health"></div>
                                    <svg x-show="account.health >= 80" class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-slate-500 mb-1">Sent Today</p>
                                <div class="text-sm font-medium text-slate-900 dark:text-slate-100" x-text="account.sent_today + ' / ' + account.daily_limit"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-auto border-t border-slate-200 dark:border-slate-800 p-4 bg-slate-50 dark:bg-slate-800/50 flex justify-between items-center rounded-b-xl">
                        <button class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Edit</button>
                        <button @click="testConnection(account)" class="text-xs font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 flex items-center gap-1">
                            <span x-show="!account.testing">Test Connection</span>
                            <span x-show="account.testing">Testing...</span>
                        </button>
                    </div>
                </x-card>
            </template>
        </div>

        <!-- Add Modal (Simplified logic via alpine) -->
        <div x-show="isAdding" x-cloak class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div x-show="isAdding" x-transition.opacity class="fixed inset-0 bg-slate-900/75 transition-opacity"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div x-show="isAdding" x-transition.scale.95 @click.away="isAdding = false" class="relative transform overflow-hidden rounded-xl bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-800">
                        <div class="px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <h3 class="text-base font-semibold leading-6 text-slate-900 dark:text-slate-100" id="modal-title">Add SMTP Account</h3>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name / Label</label>
                                    <input type="text" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950 shadow-sm sm:text-sm">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Host</label>
                                        <input type="text" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950 shadow-sm sm:text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Port</label>
                                        <input type="number" value="587" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950 shadow-sm sm:text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-800/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <x-button variant="primary" @click="isAdding = false; window.$toast('Account added', 'success')" class="sm:ml-3">Save Account</x-button>
                            <x-button variant="ghost" @click="isAdding = false" class="mt-3 sm:mt-0">Cancel</x-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('smtpManager', () => ({
                isAdding: false,
                accounts: [
                    { id: 1, name: 'Mailgun Primary', host: 'smtp.mailgun.org', port: 587, status: 'active', health: 100, sent_today: 450, daily_limit: 5000, testing: false },
                    { id: 2, name: 'AWS SES Fallback', host: 'email-smtp.us-east-1.amazonaws.com', port: 587, status: 'active', health: 95, sent_today: 120, daily_limit: 10000, testing: false },
                    { id: 3, name: 'SendGrid Suspended', host: 'smtp.sendgrid.net', port: 587, status: 'error', health: 30, sent_today: 0, daily_limit: 100, testing: false },
                ],
                testConnection(account) {
                    account.testing = true;
                    setTimeout(() => {
                        account.testing = false;
                        if(account.status === 'error') {
                            window.$toast('Connection failed: authentication error', 'error');
                        } else {
                            window.$toast('Connection successful!', 'success');
                        }
                    }, 1500);
                }
            }))
        })
    </script>
    @endpush
</x-layouts.app>
