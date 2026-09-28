<x-layouts.app header="Audience">
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Global Audience</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage your subscribers across all lists.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 w-full lg:w-auto" x-data="{ importOpen: false, addOpen: false }">
            {{-- Filter by List --}}
            <form method="GET" action="{{ route('contacts.index') }}" class="flex flex-col sm:flex-row items-center gap-2 w-full sm:w-auto">
                <select name="list_id" onchange="this.form.submit()" class="input py-2">
                    <option value="">All Subscribers</option>
                    @foreach($lists as $list)
                        <option value="{{ $list->id }}" {{ request('list_id') == $list->id ? 'selected' : '' }}>
                            {{ $list->name }}
                        </option>
                    @endforeach
                </select>
                <select name="tag_id" onchange="this.form.submit()" class="input py-2">
                    <option value="">All Tags</option>
                    @foreach($tags as $tag)
                        <option value="{{ $tag->id }}" {{ request('tag_id') == $tag->id ? 'selected' : '' }}>
                            {{ $tag->name }}
                        </option>
                    @endforeach
                </select>
            </form>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button @click="importOpen = true" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-white/5 text-slate-700 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-white/10 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-white/10 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Sync CSV
                </button>

                <button @click="addOpen = true" class="flex-1 sm:flex-none btn-gradient">
                    Add Subscriber
                </button>
            </div>

            {{-- Import CSV Modal --}}
            <x-modal x-show="importOpen" @close-modal="importOpen = false" title="Import Contacts">
                <form action="{{ route('contacts.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4" x-data="{ loading: false }" @submit="loading = true">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Upload CSV File</label>
                        <input type="file" name="csv_file" accept=".csv,.txt" required class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/50 dark:file:text-indigo-300">
                        <p class="mt-2 text-xs text-slate-500">Must include an "email" column. Over 10,000 rows may take a moment.</p>
                        @error('csv_file')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Add to List (Optional)</label>
                        <select name="list_id" class="input @error('list_id') ring-rose-400 dark:ring-rose-500 @enderror">
                            @error('list_id')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                            <option value="">No List</option>
                            @foreach($lists as $list)
                                <option value="{{ $list->id }}">{{ $list->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-5 sm:mt-6 flex justify-end gap-3">
                        <x-button type="button" @click="importOpen = false" variant="ghost">Cancel</x-button>
                        <x-button type="submit" variant="primary">
                            <span x-show="!loading">Start Import</span>
                            <span x-show="loading">Importing...</span>
                        </x-button>
                    </div>
                </form>
            </x-modal>

            {{-- Add Single Contact Modal --}}
            <x-modal x-show="addOpen" @close-modal="addOpen = false" title="Add Contact">
                <form action="{{ route('contacts.store') }}" method="POST" class="space-y-4" x-data="{ saving: false }" @submit="saving = true">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email Address</label>
                        <input type="email" name="email" required class="input">
                        @error('email')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">First Name</label>
                            <input type="text" name="first_name" class="input @error('first_name') ring-rose-400 dark:ring-rose-500 @enderror" value="{{ old('first_name') }}">
                            @error('first_name')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Last Name</label>
                            <input type="text" name="last_name" class="input @error('last_name') ring-rose-400 dark:ring-rose-500 @enderror" value="{{ old('last_name') }}">
                            @error('last_name')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Add to List</label>
                        <select name="list_id" class="input @error('list_id') ring-rose-400 dark:ring-rose-500 @enderror">
                            @error('list_id')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                            <option value="">No List</option>
                            @foreach($lists as $list)
                                <option value="{{ $list->id }}" {{ request('list_id') == $list->id ? 'selected' : '' }}>{{ $list->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Tags (comma separated)</label>
                        <input type="text" name="tags" placeholder="vip, holiday_shopper, lead" class="input @error('tags') ring-rose-400 dark:ring-rose-500 @enderror" value="{{ old('tags') }}">
                        @error('tags')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>
                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                        <x-button type="submit" variant="primary" class="w-full sm:col-start-2 justify-center" x-bind:disabled="saving">
                        <svg x-show="saving" class="w-4 h-4 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-show="!saving">Add Contact</span><span x-show="saving">Adding...</span>
                    </x-button>
                        <x-button type="button" @click="addOpen = false" variant="ghost" class="mt-3 w-full sm:col-start-1 sm:mt-0 justify-center">Cancel</x-button>
                    </div>
                </form>
            </x-modal>
        </div>
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tags</th>
                        <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Added</th>
                        <th class="px-6 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-900">
                    @forelse($contacts as $contact)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">
                                {{ $contact->email }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                {{ trim($contact->first_name . ' ' . $contact->last_name) ?: '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($contact->status === 'active')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">Subscribed</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400">{{ ucfirst($contact->status) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex gap-1 flex-wrap">
                                    @foreach($contact->tags as $tag)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-900/20 dark:text-indigo-400 dark:border-indigo-800">{{ $tag->name }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                {{ $contact->created_at->format('M j, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <form action="{{ route('contacts.destroy', $contact->id) }}" method="POST" onsubmit="return confirm('Delete this contact globally?');" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-900 dark:text-rose-400 dark:hover:text-rose-300">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">No contacts found</h3>
                                <p class="mt-1 text-sm text-slate-500">Import a CSV or add your first subscriber.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($contacts->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                {{ $contacts->links() }}
            </div>
        @endif
    </x-card>
</x-layouts.app>



