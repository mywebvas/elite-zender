<x-layouts.app header="SMTP Pool">
    <div class="max-w-7xl mx-auto" x-data="{
        modalOpen: {{ $errors->any() ? 'true' : 'false' }},
        isEditing: false,
        saving: false,
        formAction: '{{ route('smtp-accounts.store') }}',
        formData: {
            name: '{{ old('name', '') }}',
            host: '{{ old('host', '') }}',
            port: '{{ old('port', 587) }}',
            encryption: '{{ old('encryption', 'tls') }}',
            username: '{{ old('username', '') }}',
            password: '',
            from_name: '{{ old('from_name', '') }}',
            from_email: '{{ old('from_email', '') }}',
            daily_limit: '{{ old('daily_limit', 1000) }}'
        },
        openAdd() {
            this.isEditing = false;
            this.formAction = '{{ route('smtp-accounts.store') }}';
            this.formData = { name: '', host: '', port: 587, encryption: 'tls', username: '', password: '', from_name: '', from_email: '', daily_limit: 1000 };
            this.modalOpen = true;
        },
        openEdit(account) {
            this.isEditing = true;
            this.formAction = '/smtp-accounts/' + account.id;
            this.formData = {
                name: account.name || '',
                host: account.host || '',
                port: account.port || 587,
                encryption: account.encryption || 'tls',
                username: account.username || '',
                password: '',
                from_name: account.from_name || '',
                from_email: account.from_email || '',
                daily_limit: account.daily_limit || 1000
            };
            this.modalOpen = true;
        }
    }">
        <div class="sm:flex sm:items-center sm:justify-between mb-6 pb-2 border-b border-slate-200/80 dark:border-white/[0.06]">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">SMTP Relay Pool</h1>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Connect your SMTP relays for automated sending, pool rotation, and quota management.</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <x-button variant="primary" @click="openAdd()" class="text-xs">
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Relay
                </x-button>
            </div>
        </div>

        @if($accounts->isNotEmpty())
        @php
            $totalRelays = $accounts->count();
            $activeRelays = $accounts->where('status', 'active')->count();
            $totalDailyQuota = $accounts->sum('daily_limit');
            $totalSentToday = $accounts->sum('sent_today');
            $avgHealth = round($accounts->avg('health_score') ?? 100);
        @endphp
        {{-- Pool Capacity Summary Strip --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="card p-4">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Relays</p>
                <p class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">
                    {{ $activeRelays }} <span class="text-xs font-normal text-slate-400">/ {{ $totalRelays }}</span>
                </p>
            </div>
            <div class="card p-4">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Daily Capacity</p>
                <p class="text-xl font-extrabold text-slate-900 dark:text-white font-mono mt-1">
                    {{ number_format($totalDailyQuota) }}
                </p>
            </div>
            <div class="card p-4">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Sent Today</p>
                <p class="text-xl font-extrabold text-slate-900 dark:text-white font-mono mt-1">
                    {{ number_format($totalSentToday) }}
                </p>
            </div>
            <div class="card p-4">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Avg Health Score</p>
                <p class="text-xl font-extrabold mt-1 {{ $avgHealth >= 80 ? 'text-emerald-500' : ($avgHealth >= 50 ? 'text-amber-500' : 'text-rose-500') }}">
                    {{ $avgHealth }}<span class="text-xs font-normal text-slate-400">/100</span>
                </p>
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($accounts as $account)
                <x-card class="flex flex-col relative" x-data="{ testing: false }">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 truncate">{{ $account->name }}</h3>
                            <x-badge color="{{ $account->status === 'active' ? 'emerald' : ($account->status === 'error' ? 'rose' : 'slate') }}">
                                {{ ucfirst($account->status) }}
                            </x-badge>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 font-mono truncate">{{ $account->host }}:{{ $account->port }}</p>
                        
                        <div class="mt-6 flex items-end justify-between">
                            <div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-1 font-medium">Health Score</p>
                                <div class="flex items-center gap-2">
                                    <div class="text-2xl font-bold {{ $account->health_score >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($account->health_score >= 50 ? 'text-amber-500' : 'text-rose-600 dark:text-rose-400') }}">
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
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-1 font-medium">Sent Today</p>
                                <div class="text-sm font-semibold text-slate-900 dark:text-slate-100 tabular-nums">
                                    {{ number_format($account->sent_today ?? 0) }} / {{ number_format($account->daily_limit) }}
                                </div>
                            </div>
                        </div>

                        {{-- Quota progress bar --}}
                        @php
                            $quotaPct = $account->daily_limit > 0 ? min(100, (int) round(($account->sent_today ?? 0) / $account->daily_limit * 100)) : 0;
                            $barColor = $quotaPct > 90 ? 'bg-rose-500' : ($quotaPct > 70 ? 'bg-amber-500' : 'bg-emerald-500');
                        @endphp
                        <div class="mt-3 w-full bg-slate-100 dark:bg-white/[0.06] h-1.5 rounded-full overflow-hidden">
                            <div class="{{ $barColor }} h-full rounded-full transition-all duration-300" style="width: {{ $quotaPct }}%;"></div>
                        </div>
                    </div>

                    <div class="mt-auto border-t border-slate-100 dark:border-white/[0.06] p-4 bg-slate-50/70 dark:bg-white/[0.02] flex justify-between items-center rounded-b-xl">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="openEdit(@js($account))"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors">
                                Edit
                            </button>
                            <span class="text-slate-300 dark:text-white/10">|</span>
                            <form action="{{ route('smtp-accounts.destroy', $account) }}" method="POST"
                                  x-data="{ deleting: false }"
                                  @submit="if(deleting){ $event.preventDefault(); return false; } if(!confirm('Are you sure you want to remove this SMTP relay?')){ return false; } deleting = true;" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" :disabled="deleting"
                                        :class="{ 'opacity-50 cursor-not-allowed': deleting }"
                                        class="text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 transition-colors">
                                    <span x-text="deleting ? 'Deleting...' : 'Delete'">Delete</span>
                                </button>
                            </form>
                        </div>

                        <form method="POST" action="{{ route('smtp-accounts.test', $account->id) }}"
                              x-data="{ testing: false }" @submit="if(testing){ $event.preventDefault(); return false; } testing = true;">
                            @csrf
                            <button type="submit" :disabled="testing"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 disabled:opacity-60 dark:text-slate-300 dark:bg-white/5 dark:border-white/10 dark:hover:bg-white/10 transition-colors">
                                <svg x-show="testing" x-cloak class="w-3 h-3 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span x-show="!testing">Test connection</span>
                                <span x-show="testing" x-cloak>Testing…</span>
                            </button>
                        </form>
                    </div>
                </x-card>
            @empty
                <div class="col-span-full">
                    <x-empty-state
                        heading="No sending relays connected"
                        body="Connect an SMTP relay (SendGrid, Mailgun, Amazon SES, Postmark, or custom SMTP) to start sending broadcasts with dedicated IP health scoring.">
                        <x-button @click="openAdd()" variant="primary">
                            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Connect First Relay
                        </x-button>
                    </x-empty-state>
                </div>
            @endforelse
        </div>

        {{-- Add / Edit Modal --}}
        <x-modal x-show="modalOpen" @close-modal="modalOpen = false" alpine-title="isEditing ? 'Edit SMTP Relay' : 'Connect SMTP Relay'">
            <form :action="formAction" method="POST" class="space-y-4" @submit="if(saving){ $event.preventDefault(); return false; } saving = true;">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                @if ($errors->any())
                    <div class="rounded-xl border border-rose-100 bg-rose-50 p-4 dark:border-rose-900/50 dark:bg-rose-900/20" role="alert">
                        <p class="text-sm font-medium text-rose-800 dark:text-rose-300">Please fix the following:</p>
                        <ul class="mt-2 list-disc pl-5 text-sm text-rose-700 dark:text-rose-400">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <label class="input-label">Relay Name / Label</label>
                    <input type="text" name="name" x-model="formData.name" required class="input" placeholder="e.g. Primary SES / SendGrid">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="input-label">SMTP Host</label>
                        <input type="text" name="host" x-model="formData.host" required class="input font-mono text-sm" placeholder="smtp.sendgrid.net">
                    </div>
                    <div>
                        <label class="input-label">Port</label>
                        <input type="number" name="port" x-model="formData.port" required class="input font-mono text-sm" placeholder="587">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="input-label">Encryption</label>
                        <select name="encryption" x-model="formData.encryption" class="input">
                            <option value="tls">TLS (STARTTLS - recommended)</option>
                            <option value="ssl">SSL (Port 465)</option>
                            <option value="none">None (Plaintext)</option>
                        </select>
                    </div>
                    <div>
                        <label class="input-label">Daily Limit</label>
                        <input type="number" name="daily_limit" x-model="formData.daily_limit" class="input tabular-nums" placeholder="10000">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="input-label">Username</label>
                        <input type="text" name="username" x-model="formData.username" class="input font-mono text-sm" placeholder="apikey">
                    </div>
                    <div>
                        <label class="input-label">Password / API Key</label>
                        <input type="password" name="password" class="input" :placeholder="isEditing ? 'Leave blank to keep unchanged' : '••••••••'">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="input-label">Default From Name</label>
                        <input type="text" name="from_name" x-model="formData.from_name" required class="input" placeholder="Acme Team">
                    </div>
                    <div>
                        <label class="input-label">Default From Email</label>
                        <input type="email" name="from_email" x-model="formData.from_email" required class="input font-mono text-sm" placeholder="newsletter@acme.com">
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-white/[0.06]">
                    <x-button type="button" variant="ghost" @click="modalOpen = false">Cancel</x-button>
                    <x-button type="submit" variant="primary" ::disabled="saving">
                        <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="saving ? 'Saving…' : (isEditing ? 'Update Relay' : 'Save Relay')"></span>
                    </x-button>
                </div>
            </form>
        </x-modal>
    </div>
</x-layouts.app>
