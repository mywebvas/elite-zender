<x-layouts.admin title="Operators" subtitle="Who can reach this console, and how far.">
    {{-- Header Action & Security Bar --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold tracking-tight text-white">Platform Operators</h2>
                    <span class="inline-flex items-center rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-0.5 text-xs font-mono font-medium text-slate-300">
                        {{ $admins->count() }} operators
                    </span>
                </div>
                <p class="text-xs text-slate-400">Manage internal staff roles, deactivations, and temporary password credentials.</p>
            </div>
        </div>

        @if(session('new_admin_password'))
            <div class="flex items-center gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-2.5 text-xs text-amber-200 shadow-lg">
                <svg class="h-4 w-4 shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                <div class="font-mono">
                    <span class="text-slate-400">Temporary Operator Password:</span>
                    <strong class="ml-1 text-white select-all">{{ session('new_admin_password') }}</strong>
                </div>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3">
        {{-- Left 2 Columns: Operator Roster --}}
        <div class="xl:col-span-2 space-y-6">
            @foreach($admins as $admin)
                <div class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 shadow-xl backdrop-blur-xl transition hover:border-white/20 {{ $admin->is_active ? '' : 'opacity-65' }}">
                    <form method="POST" action="{{ route('admin.team.update', $admin->id) }}" class="p-6">
                        @csrf
                        @method('PUT')

                        {{-- Operator Header Info --}}
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between border-b border-white/[0.08] pb-4">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-gradient-to-br from-white/[0.08] to-white/[0.02] text-sm font-bold text-amber-300">
                                    {{ Str::of($admin->name)->substr(0, 1)->upper() }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-semibold text-white truncate">{{ $admin->name }}</h3>
                                        @if($admin->is(auth('admin')->user()))
                                            <span class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-mono font-bold text-amber-300 border border-amber-500/30">YOU</span>
                                        @endif
                                    </div>
                                    <p class="font-mono text-xs text-slate-400 truncate">{{ $admin->email }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span @class([
                                    'rounded-full px-2.5 py-0.5 text-[10px] font-mono font-bold uppercase tracking-wider border',
                                    'bg-emerald-500/15 text-emerald-300 border-emerald-500/20' => $admin->is_active,
                                    'bg-slate-500/15 text-slate-400 border-slate-500/20' => ! $admin->is_active,
                                ])>
                                    {{ $admin->is_active ? 'Active' : 'Deactivated' }}
                                </span>
                            </div>
                        </div>

                        {{-- Metadata Line --}}
                        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500 font-mono">
                            <span>
                                {{ $admin->last_login_at ? 'Last signed in '.$admin->last_login_at->diffForHumans() : 'Never signed in' }}
                            </span>
                            @if($admin->impersonations_count > 0)
                                <span>·</span>
                                <span class="rounded bg-white/[0.04] px-1.5 py-0.5 text-slate-300">
                                    {{ $admin->impersonations_count }} {{ Str::plural('impersonation', $admin->impersonations_count) }}
                                </span>
                            @endif
                        </div>

                        {{-- Editable Fields Grid --}}
                        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-slate-300">Display Name</label>
                                <input name="name" value="{{ $admin->name }}" required maxlength="80"
                                       class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-slate-300">Assigned Privilege Role</label>
                                <div class="relative">
                                    <select name="role"
                                            class="w-full appearance-none rounded-xl border-0 bg-[#0e0e18] px-3.5 py-2.5 pr-8 text-sm text-white ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                                        @foreach($roles as $value => $label)
                                            <option value="{{ $value }}" @selected($admin->role === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Action Buttons Toolbar --}}
                        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-white/[0.08] pt-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="submit" form="toggle-{{ $admin->id }}"
                                        class="rounded-xl px-3.5 py-2 text-xs font-semibold transition active:scale-[0.98] {{ $admin->is_active ? 'border border-rose-500/30 bg-rose-500/10 text-rose-300 hover:bg-rose-500/20' : 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20' }}">
                                    {{ $admin->is_active ? 'Deactivate' : 'Reinstate' }}
                                </button>

                                <button type="submit" form="reset-{{ $admin->id }}"
                                        class="rounded-xl border border-white/10 bg-white/[0.02] px-3.5 py-2 text-xs font-medium text-slate-300 transition hover:bg-white/[0.06] hover:text-white active:scale-[0.98]">
                                    Reset password
                                </button>
                            </div>

                            <button type="submit"
                                    class="rounded-xl bg-amber-500 px-5 py-2 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-[0.98]">
                                Save changes
                            </button>
                        </div>
                    </form>

                    {{-- Separate action forms to ensure clean HTML and standard CSRF/methods --}}
                    <form id="toggle-{{ $admin->id }}" method="POST" action="{{ route('admin.team.toggle', $admin->id) }}" class="hidden"
                          onsubmit="return confirm('{{ $admin->is_active ? 'Deactivate' : 'Reinstate' }} {{ $admin->email }}? Access will terminate immediately.')">
                        @csrf
                    </form>

                    <form id="reset-{{ $admin->id }}" method="POST" action="{{ route('admin.team.reset-password', $admin->id) }}" class="hidden"
                          onsubmit="return confirm('Generate and issue a new temporary password for {{ $admin->email }}?')">
                        @csrf
                    </form>
                </div>
            @endforeach
        </div>

        {{-- Right Column: Create Operator Form --}}
        <div>
            <form method="POST" action="{{ route('admin.team.store') }}"
                  class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 p-6 shadow-xl backdrop-blur-xl space-y-5">
                @csrf
                <div class="border-b border-white/[0.08] pb-4">
                    <h2 class="text-base font-semibold text-white">Add an operator</h2>
                    <p class="mt-0.5 text-xs text-slate-400">A high-entropy temporary password will be generated and shown once.</p>
                </div>

                <div>
                    <label for="new-name" class="mb-1.5 block text-xs font-medium text-slate-300">Full Name</label>
                    <input id="new-name" name="name" required maxlength="80" placeholder="e.g. Sarah Connor"
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                </div>

                <div>
                    <label for="new-email" class="mb-1.5 block text-xs font-medium text-slate-300">Staff Email</label>
                    <input id="new-email" name="email" type="email" required placeholder="staff@elitesender.app"
                           class="w-full rounded-xl border-0 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                </div>

                <div>
                    <label for="new-role" class="mb-1.5 block text-xs font-medium text-slate-300">Privilege Role</label>
                    <div class="relative">
                        <select id="new-role" name="role"
                                class="w-full appearance-none rounded-xl border-0 bg-[#0e0e18] px-3.5 py-2.5 pr-8 text-sm text-white ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                            @foreach($roles as $value => $label)
                                <option value="{{ $value }}" @selected($value === 'support')>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>

                <button type="submit"
                        class="w-full rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-[0.98]">
                    Create operator
                </button>
            </form>
        </div>
    </div>
</x-layouts.admin>
