<x-layouts.app header="Lists">

<div class="max-w-7xl mx-auto" x-data="{ open: false, saving: false }">
    <div class="page-header">
        <div>
            <h1 class="page-title">Contact Lists</h1>
            <p class="page-subtitle">Organise your subscribers into targeted lists for campaigns and drip sequences.</p>
        </div>
        <div>
            <button type="button" @click="open = true" class="btn-gradient">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                New List
            </button>

            <x-modal x-show="open" @close-modal="open = false" title="Create New List">
                <form action="{{ route('lists.store') }}" method="POST" @submit="if(saving){ $event.preventDefault(); return false; } saving = true;" class="space-y-5">
                    @csrf
                    <div>
                        <label class="input-label" for="list-name">List Name</label>
                        <input id="list-name" type="text" name="name" required class="input" placeholder="e.g. Newsletter Subscribers">
                        @error('name')<p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="input-label" for="list-description">Description <span class="text-slate-400 font-normal">(optional)</span></label>
                        <textarea id="list-description" name="description" rows="3" class="input resize-none" placeholder="What is this list for?"></textarea>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <x-button type="submit" variant="primary" class="flex-1 justify-center" x-bind:disabled="saving">
                            <svg x-show="saving" class="w-4 h-4 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-show="!saving">Create List</span><span x-show="saving">Creating…</span>
                        </x-button>
                        <x-button type="button" @click="open = false" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-modal>
        </div>
    </div>

    @if($lists->isEmpty())
        <div class="card p-12">
            <x-empty-state
                heading="No lists yet"
                body="Create your first subscriber list to organize contacts and launch targeted broadcasts.">
                <button type="button" @click="open = true" class="btn-gradient">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create Your First List
                </button>
            </x-empty-state>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($lists as $list)
            <div class="card p-6 flex flex-col gap-4 hover:border-indigo-500/30 transition-all duration-200">
                <div class="flex items-start justify-between gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/15 flex items-center justify-center flex-shrink-0 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <form action="{{ route('lists.destroy', $list->id) }}" method="POST"
                          x-data="{ deleting: false }"
                          @submit="if(deleting){ $event.preventDefault(); return false; } if(!confirm('Are you sure you want to delete this list? Contacts in this list will not be deleted.')){ return false; } deleting = true;" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" :disabled="deleting" title="Delete list"
                                :class="{ 'opacity-50 cursor-not-allowed': deleting }"
                                class="p-1.5 rounded-lg text-slate-300 dark:text-slate-600 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                </div>

                <div class="flex-1 min-w-0">
                    <a href="{{ route('contacts.index', ['list_id' => $list->id]) }}"
                       class="font-semibold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors truncate block">
                        {{ $list->name }}
                    </a>
                    @if($list->description)
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2 leading-relaxed">{{ $list->description }}</p>
                    @else
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 italic">No description provided</p>
                    @endif
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-white/[0.05]">
                    <div>
                        <p class="text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white">{{ number_format($list->contacts_count) }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">subscribers</p>
                    </div>
                    <a href="{{ route('contacts.index', ['list_id' => $list->id]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 text-xs font-semibold hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors">
                        View contacts
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

</x-layouts.app>
