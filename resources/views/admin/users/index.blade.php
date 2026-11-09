<x-layouts.admin title="Customer Users" subtitle="Global customer directory across all workspaces. Filter by role, search by email or name, and manage security sessions.">
    {{-- Header Action & Stats Bar --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold tracking-tight text-white">Users Directory</h2>
                    <span class="inline-flex items-center rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-0.5 text-xs font-mono font-medium text-slate-300">
                        {{ number_format($users->total()) }} {{ Str::plural('account', $users->total()) }}
                    </span>
                </div>
                <p class="text-xs text-slate-400">Search by name or email across all registered workspace teams.</p>
            </div>
        </div>

        @if(session('new_user_password'))
            <div class="flex items-center gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-2.5 text-xs text-amber-200">
                <svg class="h-4 w-4 shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                <div class="font-mono">
                    <span class="text-slate-400">Temporary Password:</span>
                    <strong class="ml-1 text-white select-all">{{ session('new_user_password') }}</strong>
                </div>
            </div>
        @endif
    </div>

    {{-- Filter & Search Toolbar --}}
    <div class="mb-6 rounded-2xl border border-white/[0.08] bg-[#0a0a12]/80 p-4 backdrop-blur-xl shadow-lg">
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-col gap-3 md:flex-row md:items-center">
            {{-- Search Input --}}
            <div class="relative flex-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="search" name="search" value="{{ $search }}" placeholder="Search by email address or full name…"
                       @input.debounce.400ms="$el.form.requestSubmit()"
                       class="w-full rounded-xl border-0 bg-white/[0.04] py-2.5 pl-10 pr-4 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-500 transition focus:bg-white/[0.07] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
            </div>

            {{-- Role Selector --}}
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative min-w-[140px]">
                    <select name="role"
                            @change="$el.form.requestSubmit()"
                            class="w-full appearance-none rounded-xl border-0 bg-white/[0.04] py-2.5 pl-3.5 pr-8 text-sm text-white ring-1 ring-white/10 transition focus:bg-[#0e0e18] focus:outline-none focus:ring-2 focus:ring-amber-500/60">
                        <option value="" class="bg-[#0e0e18] text-white">All roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role }}" class="bg-[#0e0e18] text-white" @selected(request('role') === $role)>
                                {{ ucfirst($role) }}
                            </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>

                {{-- Submit Button --}}
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-[0.98]">
                    <span>Filter</span>
                </button>

                @if($search !== '' || request('role'))
                    <a href="{{ route('admin.users.index') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-white/[0.08] hover:text-white">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Content Table / Mobile Cards Container --}}
    <div class="rounded-2xl border border-white/[0.08] bg-[#0a0a12]/90 shadow-xl overflow-hidden backdrop-blur-sm">
        @if($users->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.02] text-slate-500">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-semibold text-white">No customer users found</h3>
                <p class="mt-1 text-sm text-slate-400">
                    @if($search !== '' || request('role'))
                        No accounts match your current search criteria. Try modifying your search term or clearing the filter.
                    @else
                        There are currently no customer accounts registered in the database.
                    @endif
                </p>
                @if($search !== '' || request('role'))
                    <div class="mt-5">
                        <a href="{{ route('admin.users.index') }}"
                           class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2 text-xs font-semibold text-white transition hover:bg-white/[0.08]">
                            Reset search filters
                        </a>
                    </div>
                @endif
            </div>
        @else
            {{-- Desktop Data Table (hidden on mobile, visible on lg) --}}
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/[0.08] bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-4">Customer User</th>
                            <th class="px-6 py-4">Role</th>
                            <th class="px-6 py-4">Workspace</th>
                            <th class="px-6 py-4">Joined</th>
                            <th class="px-6 py-4 text-right">Security Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        @foreach($users as $user)
                            <tr class="group transition hover:bg-white/[0.02]">
                                {{-- User Name & Email --}}
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-gradient-to-br from-white/[0.06] to-white/[0.02] text-xs font-semibold text-amber-300">
                                            {{ strtoupper(substr($user->name ?: $user->email, 0, 2)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-medium text-white group-hover:text-amber-300 transition-colors truncate">
                                                {{ $user->name ?: 'Unnamed user' }}
                                            </div>
                                            <div class="flex items-center gap-1.5 font-mono text-xs text-slate-400">
                                                <span>{{ $user->email }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Role Pill --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $roleStyles = match(strtolower($user->role ?? '')) {
                                            'owner' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
                                            'admin' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
                                            'member' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                                            default => 'border-slate-500/30 bg-slate-500/10 text-slate-300',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $roleStyles }}">
                                        {{ ucfirst($user->role ?: 'member') }}
                                    </span>
                                </td>

                                {{-- Tenant / Workspace Link --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->tenant)
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('admin.tenants.show', $user->tenant_id) }}"
                                               class="font-medium text-amber-400 hover:text-amber-300 hover:underline">
                                                {{ $user->tenant->name }}
                                            </a>
                                            @if($user->tenant->status !== 'active')
                                                <span class="inline-flex items-center rounded-full border border-rose-500/30 bg-rose-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-rose-400">
                                                    {{ $user->tenant->status }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs text-rose-400 font-medium">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                            No workspace
                                        </span>
                                    @endif
                                </td>

                                {{-- Registered Date --}}
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400 font-mono">
                                    {{ $user->created_at?->format('M j, Y') ?? '—' }}
                                </td>

                                {{-- Action Controls --}}
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        {{-- Impersonate Form --}}
                                        <form method="POST" action="{{ route('admin.impersonate', $user->id) }}"
                                              onsubmit="return confirm('Sign in as {{ $user->email }}? This is recorded and shown to them in their audit log.')">
                                            @csrf
                                            <input type="hidden" name="reason" value="Support request">
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-white/10 bg-white/[0.04] px-2.5 py-1.5 text-xs font-medium text-slate-200 transition hover:bg-white/[0.1] hover:text-white"
                                                    title="Assume identity for customer support reproduction">
                                                <svg class="h-3.5 w-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                                </svg>
                                                <span>Log in as</span>
                                            </button>
                                        </form>

                                        @if(auth('admin')->user()?->canManage())
                                            {{-- Sign out all sessions --}}
                                            <form method="POST" action="{{ route('admin.users.sign-out', $user->id) }}"
                                                  onsubmit="return confirm('End every active session for {{ $user->email }} immediately?')">
                                                @csrf
                                                <button type="submit"
                                                        class="rounded-lg border border-white/5 px-2.5 py-1.5 text-xs font-medium text-slate-400 transition hover:border-rose-500/30 hover:bg-rose-500/10 hover:text-rose-300"
                                                        title="Revoke session tokens across all devices">
                                                    Sign out
                                                </button>
                                            </form>

                                            {{-- Force Password Reset --}}
                                            <form method="POST" action="{{ route('admin.users.reset-password', $user->id) }}"
                                                  onsubmit="return confirm('Reset the password for {{ $user->email }} and terminate all sessions?')">
                                                @csrf
                                                <button type="submit"
                                                        class="rounded-lg border border-white/5 px-2.5 py-1.5 text-xs font-medium text-slate-400 transition hover:border-amber-500/30 hover:bg-amber-500/10 hover:text-amber-300"
                                                        title="Generate new temporary password and revoke active sessions">
                                                    Reset password
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards View (visible on mobile, hidden on lg) --}}
            <div class="lg:hidden divide-y divide-white/[0.04]">
                @foreach($users as $user)
                    @php
                        $roleStyles = match(strtolower($user->role ?? '')) {
                            'owner' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
                            'admin' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
                            'member' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                            default => 'border-slate-500/30 bg-slate-500/10 text-slate-300',
                        };
                    @endphp
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-gradient-to-br from-white/[0.06] to-white/[0.02] text-xs font-semibold text-amber-300">
                                    {{ strtoupper(substr($user->name ?: $user->email, 0, 2)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-medium text-white truncate">{{ $user->name ?: 'Unnamed user' }}</div>
                                    <div class="text-xs text-slate-400 font-mono truncate">{{ $user->email }}</div>
                                </div>
                            </div>
                            <span class="inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $roleStyles }}">
                                {{ ucfirst($user->role ?: 'member') }}
                            </span>
                        </div>

                        {{-- Workspace and date meta --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white/[0.02] p-2.5 text-xs border border-white/5">
                            <div>
                                <span class="text-slate-500">Workspace:</span>
                                @if($user->tenant)
                                    <a href="{{ route('admin.tenants.show', $user->tenant_id) }}" class="ml-1 font-medium text-amber-400 hover:underline">
                                        {{ $user->tenant->name }}
                                    </a>
                                @else
                                    <span class="ml-1 text-rose-400 font-medium">None</span>
                                @endif
                            </div>
                            <div class="text-slate-400 font-mono">
                                {{ $user->created_at?->format('M j, Y') ?? '—' }}
                            </div>
                        </div>

                        {{-- Mobile Action Buttons Strip --}}
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <form method="POST" action="{{ route('admin.impersonate', $user->id) }}" class="flex-1"
                                  onsubmit="return confirm('Sign in as {{ $user->email }}? This is recorded and shown to them.')">
                                @csrf
                                <input type="hidden" name="reason" value="Support request">
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.06] px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-white/[0.12] transition">
                                    <svg class="h-3.5 w-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                    </svg>
                                    Log in as
                                </button>
                            </form>

                            @if(auth('admin')->user()?->canManage())
                                <form method="POST" action="{{ route('admin.users.sign-out', $user->id) }}"
                                      onsubmit="return confirm('End every session for {{ $user->email }}?')">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-lg border border-white/10 bg-white/[0.02] px-3 py-2 text-xs font-medium text-slate-300 hover:border-rose-500/30 hover:bg-rose-500/10 hover:text-rose-300 transition">
                                        Sign out
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.users.reset-password', $user->id) }}"
                                      onsubmit="return confirm('Reset password for {{ $user->email }}?')">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-lg border border-white/10 bg-white/[0.02] px-3 py-2 text-xs font-medium text-slate-300 hover:border-amber-500/30 hover:bg-amber-500/10 hover:text-amber-300 transition">
                                        Reset pass
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Pagination Bar --}}
    @if($users->hasPages())
        <div class="mt-6">
            {{ $users->links() }}
        </div>
    @endif
</x-layouts.admin>
