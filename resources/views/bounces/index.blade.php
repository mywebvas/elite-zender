<x-layouts.app header="Bounce Shield™">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-card class="p-6 relative overflow-hidden group">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-purple-500/5 dark:from-indigo-500/10 dark:to-purple-500/10 z-0"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Bounces</h3>
                        <span class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-600 dark:text-indigo-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </span>
                    </div>
                    <p class="mt-4 text-3xl font-bold text-slate-900 dark:text-white tracking-tight">0</p>
                    <p class="mt-1 text-sm text-green-600 dark:text-green-400 font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Healthy sender score
                    </p>
                </div>
            </x-card>
            
            <x-card class="p-6 relative overflow-hidden group">
                <div class="absolute inset-0 bg-gradient-to-br from-rose-500/5 to-orange-500/5 dark:from-rose-500/10 dark:to-orange-500/10 z-0"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-slate-500 dark:text-slate-400">Hard Bounces</h3>
                        <span class="p-2 bg-rose-50 dark:bg-rose-900/30 rounded-lg text-rose-600 dark:text-rose-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </span>
                    </div>
                    <p class="mt-4 text-3xl font-bold text-slate-900 dark:text-white tracking-tight">0</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Contacts automatically suppressed</p>
                </div>
            </x-card>
            
            <x-card class="p-6 relative overflow-hidden group">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-yellow-500/5 dark:from-amber-500/10 dark:to-yellow-500/10 z-0"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-slate-500 dark:text-slate-400">Soft Bounces</h3>
                        <span class="p-2 bg-amber-50 dark:bg-amber-900/30 rounded-lg text-amber-600 dark:text-amber-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        </span>
                    </div>
                    <p class="mt-4 text-3xl font-bold text-slate-900 dark:text-white tracking-tight">0</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Will be retried automatically</p>
                </div>
            </x-card>
        </div>
        
        <!-- Table -->
        <x-card>
            <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recent Bounces</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Real-time log of IMAP bounce processing</p>
                </div>
                <x-button variant="secondary" class="text-xs" @click="window.$toast('Configure Bounce Shield IMAP settings in Settings to enable this feature.', 'info')">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Process Inbox Now
                </x-button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-white/5">
                    <thead class="bg-slate-50/50 dark:bg-white/[0.02]">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Email Address</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Campaign</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Reason</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 mb-4">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <h3 class="text-sm font-medium text-slate-900 dark:text-slate-200">No bounces recorded yet</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">When your campaigns encounter delivery failures, they will appear here.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-layouts.app>

