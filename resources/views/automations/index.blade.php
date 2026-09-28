<x-layouts.app header="Automations">

<div class="page-header">
    <div>
        <h1 class="page-title">Workflow Automations</h1>
        <p class="page-subtitle">Build trigger-based drip sequences to engage your audience automatically.</p>
    </div>
    <a href="{{ route('automations.create') }}" class="btn-gradient">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Build Automation
    </a>
</div>

@if($automations->isEmpty())
<div class="card p-16 text-center">
    <div class="w-16 h-16 rounded-2xl bg-violet-50 dark:bg-violet-500/10 flex items-center justify-center mx-auto mb-5">
        <svg class="w-8 h-8 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
    </div>
    <h3 class="text-lg font-bold text-slate-900 dark:text-white">No automations yet</h3>
    <p class="text-sm text-slate-500 mt-2 mb-6">Set up automated workflows that fire at the perfect moment.</p>
    <a href="{{ route('automations.create') }}" class="btn-gradient py-3 px-6">Create First Automation</a>
</div>
@else
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($automations as $auto)
    <div class="card p-6 flex flex-col gap-4 hover:ring-violet-500/20 transition-all duration-150">
        <div class="flex items-start justify-between gap-3">
            <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-500/15 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $auto->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-400' }}">
                {{ $auto->is_active ? 'Active' : 'Draft' }}
            </span>
        </div>

        <div class="flex-1">
            <h3 class="font-bold text-slate-900 dark:text-white">{{ $auto->name }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Trigger: {{ ucwords(str_replace('_', ' ', $auto->trigger_type)) }}
            </p>
            @if($auto->steps)
            <p class="text-xs text-slate-400 mt-0.5">{{ count($auto->steps) }} step{{ count($auto->steps) !== 1 ? 's' : '' }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2 pt-3 border-t border-slate-100 dark:border-white/[0.05]">
            <a href="{{ route('automations.edit', $auto->id) }}"
               class="flex-1 text-center py-2 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                Edit
            </a>
            <form action="{{ route('automations.destroy', $auto->id) }}" method="POST"
                  onsubmit="return confirm('Delete this automation?')" class="inline">
                @csrf @method('DELETE')
                <button type="submit" class="px-3 py-2 rounded-lg text-xs font-semibold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                    Delete
                </button>
            </form>
        </div>
    </div>
    @endforeach
</div>
@if($automations->hasPages())
<div class="mt-6">{{ $automations->links() }}</div>
@endif
@endif

</x-layouts.app>
