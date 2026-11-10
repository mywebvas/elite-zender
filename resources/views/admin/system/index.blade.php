<x-layouts.admin title="System Health" subtitle="Operational telemetry, queue lane depths, scheduled tasks, and failed job recovery.">
    {{-- Header Action & Status Bar --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold tracking-tight text-white">Infrastructure & Queues</h2>
                    <span class="inline-flex items-center rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-0.5 text-xs font-mono font-medium text-slate-300">
                        {{ count($checks) }} subsystem checks
                    </span>
                </div>
                <p class="text-xs text-slate-400">Real-time health diagnostics for Redis, PostgreSQL, queue workers, and background jobs.</p>
            </div>
        </div>
    </div>

    {{-- System Telemetry Diagnostics Grid --}}
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($checks as $check)
            <div @class([
                'relative overflow-hidden rounded-2xl border p-4.5 shadow-lg backdrop-blur-xl transition duration-200',
                'border-emerald-500/30 bg-emerald-500/[0.05] hover:border-emerald-500/50' => $check['status'] === 'ok',
                'border-rose-500/35 bg-rose-500/[0.08] hover:border-rose-500/60 shadow-rose-950/20' => $check['status'] !== 'ok',
            ])>
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <span @class([
                            'h-2.5 w-2.5 flex-shrink-0 rounded-full',
                            'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.5)]' => $check['status'] === 'ok',
                            'bg-rose-400 animate-pulse shadow-[0_0_8px_rgba(251,113,133,0.8)]' => $check['status'] !== 'ok',
                        ])></span>
                        <h3 class="text-sm font-semibold tracking-tight text-white">{{ $check['name'] }}</h3>
                    </div>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-[10px] font-mono font-bold uppercase tracking-wider',
                        'bg-emerald-500/15 text-emerald-300 border border-emerald-500/20' => $check['status'] === 'ok',
                        'bg-rose-500/20 text-rose-300 border border-rose-500/30' => $check['status'] !== 'ok',
                    ])>
                        {{ $check['status'] === 'ok' ? 'HEALTHY' : 'DEGRADED' }}
                    </span>
                </div>

                {{-- Retain exact $check['detail'] string for test assertions --}}
                <p class="mt-2.5 text-xs leading-relaxed {{ $check['status'] === 'ok' ? 'text-slate-300' : 'text-rose-200 font-medium' }}">
                    {{ $check['detail'] }}
                </p>
            </div>
        @endforeach
    </div>

    {{-- Main Operations Grid --}}
    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3">
        {{-- Left 2 Columns: Failed Jobs Telemetry & Management --}}
        <div class="xl:col-span-2 space-y-6">
            <div class="overflow-hidden rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 shadow-xl backdrop-blur-xl">
                {{-- Failed Jobs Header --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/[0.08] bg-white/[0.02] px-6 py-4">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-base font-semibold text-white">Failed jobs</h2>
                            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-0.5 text-xs font-mono font-bold {{ $failedCount > 0 ? 'text-rose-400 border-rose-500/30 bg-rose-500/10' : 'text-slate-400' }}">
                                {{ $failedCount }} total
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-400">
                            In an email delivery infrastructure, a failed job represents a customer campaign or critical import.
                        </p>
                    </div>

                    @if($failedCount > 0)
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.system.retry') }}"
                                  x-data="{ retrying: false }"
                                  @submit="if(retrying){ $event.preventDefault(); return false; } if(!confirm('Re-queue all {{ $failedCount }} failed jobs across all queue lanes?')){ return false; } retrying = true;">
                                @csrf
                                <input type="hidden" name="uuid" value="all">
                                <button type="submit" :disabled="retrying"
                                        :class="{ 'opacity-60 cursor-not-allowed': retrying }"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 px-3.5 py-2 text-xs font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-[0.98]">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <span x-text="retrying ? 'Re-queuing…' : 'Retry all'">Retry all</span>
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.system.forget') }}"
                                  x-data="{ discarding: false }"
                                  @submit="if(discarding){ $event.preventDefault(); return false; } if(!confirm('Discard all {{ $failedCount }} failed jobs permanently? This cannot be undone.')){ return false; } discarding = true;">
                                @csrf
                                <input type="hidden" name="uuid" value="all">
                                <button type="submit" :disabled="discarding"
                                        :class="{ 'opacity-60 cursor-not-allowed': discarding }"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-white/10 bg-white/[0.04] px-3.5 py-2 text-xs font-semibold text-slate-300 transition hover:border-rose-500/30 hover:bg-rose-500/10 hover:text-rose-300 active:scale-[0.98]">
                                    <svg class="h-3.5 w-3.5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span x-text="discarding ? 'Discarding…' : 'Discard all'">Discard all</span>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                {{-- Failed Jobs List --}}
                <div class="divide-y divide-white/[0.04]">
                    @forelse($failed as $job)
                        <div class="p-5 transition hover:bg-white/[0.02]">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-sm font-bold text-white">{{ $job->name }}</span>
                                        @php
                                            $queueStyle = match($job->queue) {
                                                'high' => 'bg-rose-500/15 text-rose-300 border border-rose-500/20',
                                                'default' => 'bg-amber-500/15 text-amber-300 border border-amber-500/20',
                                                'low' => 'bg-slate-500/15 text-slate-300 border border-slate-500/20',
                                                default => 'bg-white/10 text-slate-300 border border-white/10',
                                            };
                                        @endphp
                                        <span class="rounded-md px-2 py-0.5 text-[10px] font-mono font-semibold uppercase tracking-wider {{ $queueStyle }}">
                                            {{ $job->queue }}
                                        </span>
                                        <span class="text-xs text-slate-500 font-mono">ID: {{ Str::limit($job->uuid, 12) }}</span>
                                    </div>

                                    {{-- Exception Trace snippet --}}
                                    <div class="rounded-xl border border-rose-500/20 bg-rose-500/[0.04] p-3">
                                        <p class="break-all font-mono text-xs text-rose-300 leading-relaxed">{{ $job->exception }}</p>
                                    </div>

                                    <div class="flex items-center gap-2 text-xs text-slate-500 font-mono">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Failed at: {{ $job->failed_at }}</span>
                                    </div>
                                </div>

                                {{-- Job Action Buttons --}}
                                <div class="flex shrink-0 items-center gap-2 pt-1">
                                    <form method="POST" action="{{ route('admin.system.retry') }}"
                                          x-data="{ retrying: false }" @submit="if(retrying){ $event.preventDefault(); return false; } retrying = true;">
                                        @csrf
                                        <input type="hidden" name="uuid" value="{{ $job->uuid }}">
                                        <button type="submit" :disabled="retrying"
                                                :class="{ 'opacity-60 cursor-not-allowed': retrying }"
                                                class="inline-flex items-center gap-1 rounded-lg border border-white/10 bg-white/[0.04] px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:bg-white/[0.1] hover:text-white"
                                                title="Retry this specific failed job">
                                            <svg class="h-3.5 w-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            <span x-text="retrying ? 'Retrying…' : 'Retry'">Retry</span>
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.system.forget') }}"
                                          x-data="{ discarding: false }"
                                          @submit="if(discarding){ $event.preventDefault(); return false; } if(!confirm('Discard job {{ $job->uuid }}?')){ return false; } discarding = true;">
                                        @csrf
                                        <input type="hidden" name="uuid" value="{{ $job->uuid }}">
                                        <button type="submit" :disabled="discarding"
                                                :class="{ 'opacity-60 cursor-not-allowed': discarding }"
                                                class="inline-flex items-center gap-1 rounded-lg border border-white/5 bg-transparent px-2.5 py-1.5 text-xs font-medium text-slate-400 transition hover:border-rose-500/30 hover:bg-rose-500/10 hover:text-rose-300"
                                                title="Drop from failed jobs queue">
                                            <span x-text="discarding ? 'Discarding…' : 'Discard'">Discard</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-400">
                                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-semibold text-white">No failed jobs</h3>
                            <p class="mt-1 text-sm text-slate-400">All campaign dispatches, bounces, and background jobs are executing smoothly.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right Column: Queue Lane Depths & Scheduled Tasks --}}
        <div class="space-y-6">
            {{-- Queue Lane Depths --}}
            <div class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 p-5 shadow-xl backdrop-blur-xl">
                <div class="mb-4 flex items-center justify-between border-b border-white/[0.08] pb-3">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <h2 class="text-sm font-semibold text-white">Queue depth</h2>
                    </div>
                    <span class="text-[11px] font-mono text-slate-500">Redis workers</span>
                </div>

                <div class="space-y-3">
                    @foreach($queues as $queue => $depth)
                        <div class="rounded-xl border border-white/5 bg-white/[0.02] p-3">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs font-semibold uppercase text-slate-300">{{ $queue }}</span>
                                <span class="font-mono text-sm font-bold tabular-nums {{ $depth > 1000 ? 'text-amber-400' : 'text-white' }}">
                                    {{ $depth < 0 ? 'unknown' : number_format($depth) }}
                                </span>
                            </div>
                            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-white/[0.06]">
                                <div class="h-full rounded-full {{ $queue === 'high' ? 'bg-amber-500' : ($queue === 'default' ? 'bg-sky-500' : 'bg-slate-500') }}"
                                     style="width: {{ min(100, max(4, $depth > 0 ? log10($depth + 1) * 25 : 0)) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 border-t border-white/[0.06] pt-3 text-xs leading-relaxed text-slate-500">
                    <strong class="text-slate-300">high</strong> handles immediate campaign chunks. A rising depth with healthy worker heartbeats indicates worker scaling is required.
                </p>
            </div>

            {{-- Scheduled Tasks --}}
            <div class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 p-5 shadow-xl backdrop-blur-xl">
                <div class="mb-4 flex items-center justify-between border-b border-white/[0.08] pb-3">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h2 class="text-sm font-semibold text-white">Scheduled tasks</h2>
                    </div>
                    <span class="text-[11px] font-mono text-slate-500">Cron ticker</span>
                </div>

                <div class="space-y-2.5 max-h-[420px] overflow-y-auto pr-1">
                    @forelse($scheduled as $task)
                        <div class="rounded-xl border border-white/5 bg-white/[0.02] p-3 transition hover:border-white/10">
                            <p class="truncate font-mono text-xs font-semibold text-slate-200">
                                {{ $task['command'] ?: 'closure' }}
                            </p>
                            <div class="mt-1.5 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                                <span class="rounded bg-white/[0.05] px-1.5 py-0.5 text-amber-300/90">{{ $task['expression'] }}</span>
                                <span>next {{ $task['next'] }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs text-slate-500">No scheduled commands configured.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
