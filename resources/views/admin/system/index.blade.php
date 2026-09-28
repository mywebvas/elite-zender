<x-layouts.admin title="System health" subtitle="What an operator would otherwise SSH in to check.">

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($checks as $check)
            <div @class([
                'rounded-xl border p-4',
                'border-emerald-500/20 bg-emerald-500/[0.06]' => $check['status'] === 'ok',
                'border-rose-500/25 bg-rose-500/[0.08]' => $check['status'] !== 'ok',
            ])>
                <div class="flex items-center gap-2">
                    <span @class([
                        'h-2 w-2 flex-shrink-0 rounded-full',
                        'bg-emerald-400' => $check['status'] === 'ok',
                        'bg-rose-400 animate-pulse' => $check['status'] !== 'ok',
                    ])></span>
                    <p class="text-sm font-medium">{{ $check['name'] }}</p>
                </div>
                <p class="mt-1.5 text-xs {{ $check['status'] === 'ok' ? 'text-slate-400' : 'text-rose-300' }}">
                    {{ $check['detail'] }}
                </p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <div class="overflow-hidden rounded-xl border border-white/10 bg-[#0b0b14]">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.07] px-5 py-3.5">
                    <div>
                        <h2 class="text-sm font-semibold">Failed jobs</h2>
                        <p class="text-xs text-slate-500">
                            {{ $failedCount }} total. In a sending platform a failed job is usually somebody's campaign.
                        </p>
                    </div>

                    @if($failedCount > 0)
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('admin.system.retry') }}"
                                  onsubmit="return confirm('Re-queue all {{ $failedCount }} failed jobs?')">
                                @csrf
                                <input type="hidden" name="uuid" value="all">
                                <button type="submit" class="rounded-lg bg-amber-500/15 px-3 py-1.5 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/25">
                                    Retry all
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.system.forget') }}"
                                  onsubmit="return confirm('Discard all {{ $failedCount }} failed jobs? This cannot be undone.')">
                                @csrf
                                <input type="hidden" name="uuid" value="all">
                                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-400 transition hover:bg-white/5 hover:text-rose-300">
                                    Discard all
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                @forelse($failed as $job)
                    <div class="flex flex-wrap items-start gap-3 border-b border-white/5 px-5 py-3 last:border-0">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium">{{ $job->name }}</p>
                                <span class="rounded bg-white/[0.06] px-1.5 py-0.5 text-[10px] uppercase text-slate-400">{{ $job->queue }}</span>
                            </div>
                            <p class="mt-1 break-all font-mono text-[11px] text-rose-300/80">{{ $job->exception }}</p>
                            <p class="mt-1 text-[11px] text-slate-600">{{ $job->failed_at }}</p>
                        </div>
                        <div class="flex flex-shrink-0 gap-1.5">
                            <form method="POST" action="{{ route('admin.system.retry') }}">
                                @csrf
                                <input type="hidden" name="uuid" value="{{ $job->uuid }}">
                                <button type="submit" class="rounded bg-white/10 px-2 py-1 text-[11px] font-semibold transition hover:bg-white/20">Retry</button>
                            </form>
                            <form method="POST" action="{{ route('admin.system.forget') }}">
                                @csrf
                                <input type="hidden" name="uuid" value="{{ $job->uuid }}">
                                <button type="submit" class="rounded px-2 py-1 text-[11px] font-semibold text-slate-500 transition hover:text-rose-300">Discard</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-500">Nothing has failed. Good.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                <h2 class="mb-3 text-sm font-semibold">Queue depth</h2>
                @foreach($queues as $queue => $depth)
                    <div class="flex items-center justify-between py-1 text-sm">
                        <span class="text-slate-400">{{ $queue }}</span>
                        <span class="font-semibold tabular-nums {{ $depth > 1000 ? 'text-amber-400' : '' }}">
                            {{ $depth < 0 ? 'unknown' : number_format($depth) }}
                        </span>
                    </div>
                @endforeach
                <p class="mt-3 border-t border-white/[0.07] pt-3 text-[11px] text-slate-600">
                    `high` is campaign sending. A rising number here with a healthy heartbeat means you need more workers.
                </p>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                <h2 class="mb-3 text-sm font-semibold">Scheduled tasks</h2>
                @forelse($scheduled as $task)
                    <div class="border-b border-white/5 py-2 last:border-0">
                        <p class="truncate font-mono text-[11px] text-slate-300">{{ $task['command'] ?: 'closure' }}</p>
                        <p class="text-[11px] text-slate-600">{{ $task['expression'] }} · next {{ $task['next'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nothing scheduled.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.admin>
