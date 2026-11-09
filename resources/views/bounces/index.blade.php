<x-layouts.app header="Bounces">
    <div class="max-w-7xl mx-auto space-y-6">

        {{-- Page Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-white/[0.06]">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Bounce Activity</h1>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Track delivery dropoffs, hard bounce suppressions, and mailbox diagnostic feedback.</p>
            </div>
            <a href="{{ route('settings.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-white/[0.04] text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-white/[0.08] transition-colors shadow-xs self-start sm:self-auto">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>IMAP Settings</span>
            </a>
        </div>
        
        {{-- Stat Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-card class="p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Bounces</span>
                    <span class="w-2 h-2 rounded-full {{ $bounces->total() === 0 ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                </div>
                <p class="mt-3 text-2xl font-bold text-slate-900 dark:text-white tabular-nums">{{ number_format($bounces->total()) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Recorded across all campaigns</p>
            </x-card>
            
            <x-card class="p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Suppressed Contacts</span>
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                </div>
                <p class="mt-3 text-2xl font-bold text-slate-900 dark:text-white tabular-nums">{{ number_format($suppressed) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Safely blocked from future sends</p>
            </x-card>
            
            <x-card class="p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Spam Complaints</span>
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                </div>
                @php
                    $complaintCount = $bounces->getCollection()->where('type', App\Models\CampaignEvent::TYPE_COMPLAINT)->count();
                @endphp
                <p class="mt-3 text-2xl font-bold text-slate-900 dark:text-white tabular-nums">{{ number_format($complaintCount) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Feedback loop notifications</p>
            </x-card>
        </div>
        
        {{-- Activity Table Card --}}
        <x-card :padding="false">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-white/[0.06] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Recent Bounces & Complaints</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Real-time log of IMAP bounce signals and ISP delivery errors</p>
                </div>
                <a href="{{ route('settings.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-white/[0.04] text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-white/[0.08] transition-colors self-start sm:self-auto">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Configure Mailbox</span>
                </a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-white/[0.06]">
                    <thead class="bg-slate-50/70 dark:bg-white/[0.02]">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Email Address</th>
                            <th scope="col" class="px-6 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Campaign</th>
                            <th scope="col" class="px-6 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Reason / URL</th>
                            <th scope="col" class="px-6 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/[0.04] bg-white dark:bg-[#0c0d12]">
                        @forelse($bounces as $bounce)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-white/[0.02] transition-colors">
                                <td class="px-6 py-3.5 whitespace-nowrap text-xs font-semibold text-slate-900 dark:text-white">
                                    {{ $bounce->contact?->email ?? 'Direct / Suppressed' }}
                                </td>
                                <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">
                                    @if($bounce->campaign)
                                        <a href="{{ route('campaigns.show', $bounce->campaign->id) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">
                                            {{ $bounce->campaign->name }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5 whitespace-nowrap text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $bounce->type === 'complaint' ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' }}">
                                        {{ ucfirst($bounce->type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 text-xs text-slate-500 dark:text-slate-400 max-w-xs truncate">
                                    {{ $bounce->url ?: 'Delivery permanent rejection' }}
                                </td>
                                <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-400 font-mono">
                                    {{ $bounce->created_at->format('M j, Y H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-slate-500 mb-3">
                                        <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-200">No bounces recorded yet</h4>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">When sending encounters rejection or spam complaints, they will appear here automatically.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($bounces->hasPages())
                <div class="px-6 py-3 border-t border-slate-100 dark:border-white/[0.06]">
                    {{ $bounces->links() }}
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.app>
