<x-layouts.admin title="Users" subtitle="Every customer user, across every workspace.">
    <form method="GET" class="mb-4 flex flex-wrap gap-3">
        <input type="search" name="search" value="{{ $search }}" placeholder="Email or name…"
               class="min-w-0 flex-1 rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-amber-500">
        <select name="role" class="rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 focus:ring-2 focus:ring-amber-500">
            <option value="">Any role</option>
            @foreach($roles as $role)
                <option value="{{ $role }}" @selected(request('role') === $role)>{{ ucfirst($role) }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400">Search</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-white/10 bg-[#0b0b14]">
        @forelse($users as $user)
            <div class="flex flex-wrap items-center gap-4 border-b border-white/5 px-5 py-4 last:border-0">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $user->email }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $user->name }} · {{ $user->role }} ·
                        @if($user->tenant)
                            <a href="{{ route('admin.tenants.show', $user->tenant_id) }}" class="text-amber-500 hover:underline">{{ $user->tenant->name }}</a>
                            @if($user->tenant->status !== 'active')
                                <span class="text-rose-400">({{ $user->tenant->status }})</span>
                            @endif
                        @else
                            <span class="text-rose-400">no workspace</span>
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <form method="POST" action="{{ route('admin.impersonate', $user->id) }}"
                          onsubmit="return confirm('Sign in as {{ $user->email }}? This is recorded and shown to them.')">
                        @csrf
                        <input type="hidden" name="reason" value="Support request">
                        <button type="submit" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold transition hover:bg-white/20">Log in as</button>
                    </form>

                    @if(auth('admin')->user()?->canManage())
                        <form method="POST" action="{{ route('admin.users.sign-out', $user->id) }}"
                              onsubmit="return confirm('End every session for {{ $user->email }}?')">
                            @csrf
                            <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-400 transition hover:bg-white/5 hover:text-white">Sign out</button>
                        </form>
                        <form method="POST" action="{{ route('admin.users.reset-password', $user->id) }}"
                              onsubmit="return confirm('Reset the password for {{ $user->email }} and end all their sessions?')">
                            @csrf
                            <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-400 transition hover:bg-white/5 hover:text-amber-300">Reset password</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center">
                <p class="text-sm font-medium">No users matched</p>
                <p class="mt-1 text-sm text-slate-500">Try a partial email address.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.admin>
