<x-layouts.app header="SMTP Pool">
    <div class="max-w-7xl mx-auto" x-data="{ isAdding: {{ $errors->any() ? 'true' : 'false' }} }">
        <div class="sm:flex sm:items-center sm:justify-between mb-8">
            <div>
                <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">Manage your sender accounts. The system automatically rotates through healthy accounts.</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <x-button variant="primary" @click="openAdd()">Add Account</x-button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($accounts as $account)
                <x-card class="flex flex-col relative" x-data="{ testing: false }">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ $account->name }}</h3>
                            <x-badge color="{{ $account->status === 'active' ? 'emerald' : ($account->status === 'error' ? 'rose' : 'slate') }}">
                                {{ ucfirst($account->status) }}
                            </x-badge>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 truncate">{{ $account->host }}:{{ $account->port }}</p>
                        
                        <div class="mt-6 flex items-end justify-between">
                            <div>
                                <p class="text-xs text-slate-500 mb-1">Health Score</p>
                                <div class="flex items-center gap-2">
                                    <div class="text-2xl font-semibold {{ $account->health_score >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($account->health_score >= 50 ? 'text-amber-500' : 'text-rose-600 dark:text-rose-400') }}">
                                        {{ $account->health_score }}
                                    </div>
                                    @if($account->health_score >= 80)
                                        <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-slate-500 mb-1">Sent Today</p>
                                <div class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $account->sent_today ?? 0 }} / {{ $account->daily_limit }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-auto border-t border-slate-200 dark:border-slate-800 p-4 bg-slate-50 dark:bg-slate-800/50 flex justify-between items-center rounded-b-xl">
                        <div class="flex gap-4">
                            <button @click="window.$toast('Editing is not supported in this preview yet.', 'info')" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Edit</button>
                            <form action="{{ route('smtp-accounts.destroy', $account) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this account?');" class="inline">
                                @csrf
                        <template x-if="isEditing">
                            <input type="hidden" name="_method" value="PUT">
                        </template>
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-rose-600 hover:text-rose-500 dark:text-rose-400">Delete</button>
                            </form>
                        </div>
                        {{--
                            This used to be a 1.5-second setTimeout followed by
                            a "Connection test successful!" toast: it told every
                            customer their credentials worked without opening a
                            socket. Now it posts, opens a real SMTP session and
                            reports what the relay actually said.
                        --}}
                        <form method="POST" action="{{ route('smtp-accounts.test', $account->id) }}"
                              x-data="{ testing: false }" @submit="testing = true">
                            @csrf
                            <button type="submit" :disabled="testing"
                                    class="flex items-center gap-1 text-xs font-medium text-slate-600 hover:text-slate-900 disabled:opacity-60 dark:text-slate-400 dark:hover:text-slate-200">
                                <span x-show="!testing">Test connection</span>
                                <span x-show="testing" x-cloak>Connecting…</span>
                            </button>
                        </form>
                    </div>
                </x-card>
            @empty
                <div class="col-span-full py-8 text-center text-sm text-slate-500">
                    No SMTP accounts configured.
                </div>
            @endforelse
        </div>

        <!-- Add Modal -->
        <div x-show="modalOpen" x-cloak class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div x-show="modalOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/75 transition-opacity"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <form :action="formAction" method="POST" x-show="modalOpen" x-transition.scale.95 @click.away="modalOpen = false" class="relative transform overflow-hidden rounded-xl bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-800">
                        @csrf
                        <template x-if="isEditing">
                            <input type="hidden" name="_method" value="PUT">
                        </template>
                        <div class="px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <h3 class="text-base font-semibold leading-6 text-slate-900 dark:text-slate-100" id="modal-title" x-text="isEditing ? 'Edit SMTP Account' : 'Add SMTP Account'"></h3>
                            
                            @if ($errors->any())
                                <div class="mt-3 rounded-md bg-rose-50 dark:bg-rose-900/30 p-4">
                                    <div class="flex">
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-rose-800 dark:text-rose-200">There were errors with your submission</h3>
                                            <div class="mt-2 text-sm text-rose-700 dark:text-rose-300">
                                                <ul role="list" class="list-disc space-y-1 pl-5">
                                                    @foreach ($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="input-label">Name / Label</label>
                                    <input type="text" name="name" x-model="formData.name" required class="input">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="input-label">Host</label>
                                        <input type="text" name="host" x-model="formData.host" required class="input">
                                    </div>
                                    <div>
                                        <label class="input-label">Port</label>
                                        <input type="number" name="port" x-model="formData.port" required class="input">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="input-label">Username</label>
                                        <input type="text" name="username" x-model="formData.username" class="input">
                                    </div>
                                    <div>
                                        <label class="input-label">Password</label>
                                        <input type="password" name="password" class="input" :placeholder="isEditing ? 'Leave blank to keep unchanged' : ''">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="input-label">From Name</label>
                                        <input type="text" name="from_name" x-model="formData.from_name" required class="input" placeholder="Acme Sales">
                                    </div>
                                    <div>
                                        <label class="input-label">From Email</label>
                                        <input type="email" name="from_email" x-model="formData.from_email" required class="input" placeholder="hello@acme.com">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-800/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <x-button type="submit" variant="primary" class="sm:ml-3">Save Account</x-button>
                            <x-button type="button" variant="ghost" @click="modalOpen = false" class="mt-3 sm:mt-0">Cancel</x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    
</x-layouts.app>






