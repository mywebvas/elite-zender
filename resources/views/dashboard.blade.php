<x-layouts.app>
    <x-slot:header>Dashboard</x-slot:header>

    {{-- Welcome banner --}}
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
            Welcome back{{ auth()->check() ? ', ' . auth()->user()->name : '' }}! 👋
        </h2>
        <p class="mt-1 text-[13px] text-slate-500 dark:text-slate-400">
            Here's what's happening with your campaigns.
        </p>
    </div>

    {{-- Stat cards placeholder (real data in M6 Analytics) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @php
            $stats = [
                ['label' => 'Emails Sent',     'value' => '—', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8', 'color' => 'indigo'],
                ['label' => 'Open Rate',       'value' => '—', 'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z', 'color' => 'emerald'],
                ['label' => 'Click Rate',      'value' => '—', 'icon' => 'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5',   'color' => 'sky'],
                ['label' => 'Active Campaigns','value' => '—', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'color' => 'amber'],
            ];
        @endphp

        @foreach($stats as $stat)
            <x-card class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-{{ $stat['color'] }}-100 dark:bg-{{ $stat['color'] }}-900 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">{{ $stat['label'] }}</p>
                    <p class="text-xl font-bold text-slate-900 dark:text-slate-100 mt-0.5">{{ $stat['value'] }}</p>
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- Empty state: no campaigns yet --}}
    <x-card :padding="false">
        <x-empty-state
            heading="No campaigns yet"
            body="Create your first campaign and start reaching your audience. It only takes a minute."
        >
            <x-button href="#" variant="primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                New Campaign
            </x-button>
        </x-empty-state>
    </x-card>

</x-layouts.app>
