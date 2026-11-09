<x-layouts.app header="Audience">
    <div class="space-y-6" x-data="{ importOpen: false, addOpen: false }">

        {{-- Page Header & Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-white/[0.06]">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Audience</h1>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Manage subscribers, monitor delivery engagement, and segment lists.</p>
            </div>
            
            <div class="flex items-center gap-2.5">
                <button type="button" @click="importOpen = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-white/[0.04] text-slate-700 dark:text-slate-200 border border-slate-200/80 dark:border-white/10 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-white/[0.08] transition-colors shadow-xs">
                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Import CSV</span>
                </button>

                <button type="button" @click="addOpen = true" class="btn-gradient text-xs">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Subscriber</span>
                </button>
            </div>
        </div>

        {{-- Filters & Search Bar --}}
        <div class="card p-4">
            <form method="GET" action="{{ route('contacts.index') }}" class="flex flex-col md:flex-row items-center gap-3">
                {{-- Search Bar --}}
                <div class="relative flex-1 w-full">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}"
                           @input.debounce.400ms="$el.form.requestSubmit()"
                           placeholder="Search by email, first name, last name…"
                           class="input pl-10 text-xs w-full">
                </div>

                <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                    {{-- Filter by List --}}
                    <select name="list_id" onchange="this.form.submit()" class="input py-2 text-xs w-full sm:w-auto">
                        <option value="">All Lists</option>
                        @foreach($lists as $list)
                            <option value="{{ $list->id }}" @selected(request('list_id') == $list->id)>
                                {{ $list->name }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Filter by Tag --}}
                    <select name="tag_id" onchange="this.form.submit()" class="input py-2 text-xs w-full sm:w-auto">
                        <option value="">All Tags</option>
                        @foreach($tags as $tag)
                            <option value="{{ $tag->id }}" @selected(request('tag_id') == $tag->id)>
                                {{ $tag->name }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Filter by Status --}}
                    <select name="status" onchange="this.form.submit()" class="input py-2 text-xs w-full sm:w-auto">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Subscribed</option>
                        <option value="unsubscribed" @selected(request('status') === 'unsubscribed')>Unsubscribed</option>
                        <option value="bounced" @selected(request('status') === 'bounced')>Bounced</option>
                        <option value="complained" @selected(request('status') === 'complained')>Complained</option>
                    </select>

                    @if(request()->anyFilled(['search', 'list_id', 'tag_id', 'status']))
                        <a href="{{ route('contacts.index') }}" class="px-3 py-2 text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 transition-colors">
                            Reset
                        </a>
                    @else
                        <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/5 dark:hover:bg-white/10 text-xs font-semibold text-slate-700 dark:text-slate-300 transition-colors">
                            Filter
                        </button>
                    @endif
                </div>
            </form>
        </div>

        {{-- Contacts Table Card --}}
        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-white/[0.06]">
                    <thead class="bg-slate-50 dark:bg-white/[0.02]">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tags</th>
                            <th scope="col" class="px-6 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Added</th>
                            <th scope="col" class="px-6 py-3 text-right text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/[0.04] bg-white dark:bg-[#0A0E18]">
                        @forelse($contacts as $contact)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-white/[0.02] transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ $contact->email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                    {{ trim($contact->first_name . ' ' . $contact->last_name) ?: '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($contact->status === 'active')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Subscribed
                                        </span>
                                    @elseif($contact->status === 'unsubscribed')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200 dark:bg-white/5 dark:text-slate-300 dark:border-white/10">
                                            Unsubscribed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20">
                                            {{ ucfirst($contact->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex gap-1 flex-wrap">
                                        @forelse($contact->tags as $tag)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20">
                                                {{ $tag->name }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-400">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400 font-mono">
                                    {{ $contact->created_at->format('M j, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <form action="{{ route('contacts.destroy', $contact->id) }}" method="POST"
                                          onsubmit="return confirm('Delete this subscriber from your audience?');" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-900 dark:text-rose-400 dark:hover:text-rose-300 transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <x-empty-state
                                        heading="No contacts found"
                                        body="Import a subscriber list or manually add your first contact to get started with broadcasts.">
                                        <div class="flex items-center justify-center gap-3">
                                            <button type="button" @click="importOpen = true" class="btn-gradient">
                                                Import CSV
                                            </button>
                                            <button type="button" @click="addOpen = true" class="btn-secondary">
                                                Add Single Contact
                                            </button>
                                        </div>
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($contacts->hasPages())
                <div class="px-6 py-4 border-t border-slate-200 dark:border-white/[0.06]">
                    {{ $contacts->links() }}
                </div>
            @endif
        </x-card>

        {{-- Import CSV Modal --}}
        <x-modal x-show="importOpen" @close-modal="importOpen = false" title="Import Contacts">
            <form action="{{ route('contacts.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4" x-data="{ loading: false }" @submit="loading = true">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Upload CSV File</label>
                    <input type="file" name="csv_file" accept=".csv,.txt" required class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-500/10 dark:file:text-indigo-300">
                    <p class="mt-2 text-[11px] text-slate-500">Must include an "email" column header. Files over 10,000 rows process in the background.</p>
                    @error('csv_file')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Add to List (Optional)</label>
                    <select name="list_id" class="input text-xs @error('list_id') ring-rose-400 dark:ring-rose-500 @enderror">
                        <option value="">No List</option>
                        @foreach($lists as $list)
                            <option value="{{ $list->id }}">{{ $list->name }}</option>
                        @endforeach
                    </select>
                    @error('list_id')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>

                <div class="mt-6 flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-white/[0.06]">
                    <x-button type="button" @click="importOpen = false" variant="ghost">Cancel</x-button>
                    <x-button type="submit" variant="primary" x-bind:disabled="loading">
                        <svg x-show="loading" class="w-4 h-4 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-show="!loading">Start Import</span>
                        <span x-show="loading">Importing…</span>
                    </x-button>
                </div>
            </form>
        </x-modal>

        {{-- Add Single Contact Modal --}}
        <x-modal x-show="addOpen" @close-modal="addOpen = false" title="Add Contact">
            <form action="{{ route('contacts.store') }}" method="POST" class="space-y-4" x-data="{ saving: false }" @submit="saving = true">
                @csrf
                <div>
                    <label class="input-label" for="contact-email">Email Address</label>
                    <input id="contact-email" type="email" name="email" required class="input text-xs" placeholder="name@domain.com">
                    @error('email')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="input-label" for="contact-first-name">First Name</label>
                        <input id="contact-first-name" type="text" name="first_name" class="input text-xs @error('first_name') ring-rose-400 dark:ring-rose-500 @enderror" value="{{ old('first_name') }}" placeholder="Alex">
                        @error('first_name')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="input-label" for="contact-last-name">Last Name</label>
                        <input id="contact-last-name" type="text" name="last_name" class="input text-xs @error('last_name') ring-rose-400 dark:ring-rose-500 @enderror" value="{{ old('last_name') }}" placeholder="Smith">
                        @error('last_name')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="input-label" for="contact-list-id">Add to List</label>
                    <select id="contact-list-id" name="list_id" class="input text-xs @error('list_id') ring-rose-400 dark:ring-rose-500 @enderror">
                        <option value="">No List</option>
                        @foreach($lists as $list)
                            <option value="{{ $list->id }}" {{ request('list_id') == $list->id ? 'selected' : '' }}>{{ $list->name }}</option>
                        @endforeach
                    </select>
                    @error('list_id')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="input-label" for="contact-tags">Tags (comma separated)</label>
                    <input id="contact-tags" type="text" name="tags" placeholder="vip, customer, newsletter" class="input text-xs @error('tags') ring-rose-400 dark:ring-rose-500 @enderror" value="{{ old('tags') }}">
                    @error('tags')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
                <div class="mt-6 flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-white/[0.06]">
                    <x-button type="button" @click="addOpen = false" variant="ghost">Cancel</x-button>
                    <x-button type="submit" variant="primary" x-bind:disabled="saving">
                        <svg x-show="saving" class="w-4 h-4 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-show="!saving">Add Contact</span><span x-show="saving">Adding…</span>
                    </x-button>
                </div>
            </form>
        </x-modal>
    </div>
</x-layouts.app>
