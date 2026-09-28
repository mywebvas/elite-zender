<x-layouts.admin title="Operators" subtitle="Who can reach this console, and how far.">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <div class="space-y-4 xl:col-span-2">
            @foreach($admins as $admin)
                <form method="POST" action="{{ route('admin.team.update', $admin->id) }}"
                      class="rounded-xl border border-white/10 bg-[#0b0b14] p-5 {{ $admin->is_active ? '' : 'opacity-60' }}">
                    @csrf @method('PUT')

                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-white/[0.06] text-sm font-semibold">
                                {{ Str::of($admin->name)->substr(0, 1)->upper() }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $admin->email }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $admin->last_login_at ? 'Last signed in '.$admin->last_login_at->diffForHumans() : 'Never signed in' }}
                                    @if($admin->impersonations_count > 0)
                                        · {{ $admin->impersonations_count }} impersonation(s)
                                    @endif
                                </p>
                            </div>
                        </div>

                        <span @class([
                            'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                            'bg-emerald-500/15 text-emerald-400' => $admin->is_active,
                            'bg-slate-500/15 text-slate-400' => ! $admin->is_active,
                        ])>{{ $admin->is_active ? 'Active' : 'Deactivated' }}</span>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-400">Name</label>
                            <input name="name" value="{{ $admin->name }}" required maxlength="80"
                                   class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 focus:ring-2 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-400">Role</label>
                            <select name="role" class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 focus:ring-2 focus:ring-amber-500">
                                @foreach($roles as $value => $label)
                                    <option value="{{ $value }}" @selected($admin->role === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button type="submit" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold transition hover:bg-white/20">
                            Save changes
                        </button>

                        <button type="submit" form="toggle-{{ $admin->id }}"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $admin->is_active ? 'bg-rose-500/15 text-rose-300 hover:bg-rose-500/25' : 'bg-emerald-500/15 text-emerald-300 hover:bg-emerald-500/25' }}">
                            {{ $admin->is_active ? 'Deactivate' : 'Reinstate' }}
                        </button>

                        <button type="submit" form="reset-{{ $admin->id }}"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-400 transition hover:bg-white/5 hover:text-white">
                            Reset password
                        </button>
                    </div>
                </form>

                {{-- Separate forms: nested <form> is invalid HTML and silently
                     drops the inner one in every browser. --}}
                <form id="toggle-{{ $admin->id }}" method="POST" action="{{ route('admin.team.toggle', $admin->id) }}" class="hidden"
                      onsubmit="return confirm('{{ $admin->is_active ? 'Deactivate' : 'Reinstate' }} {{ $admin->email }}?')">@csrf</form>
                <form id="reset-{{ $admin->id }}" method="POST" action="{{ route('admin.team.reset-password', $admin->id) }}" class="hidden"
                      onsubmit="return confirm('Issue a new temporary password for {{ $admin->email }}?')">@csrf</form>
            @endforeach
        </div>

        <div>
            <form method="POST" action="{{ route('admin.team.store') }}" class="rounded-xl border border-white/10 bg-[#0b0b14] p-5">
                @csrf
                <h2 class="text-sm font-semibold">Add an operator</h2>
                <p class="mt-0.5 text-xs text-slate-500">A temporary password is generated and shown once.</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="new-name" class="mb-1 block text-xs font-medium text-slate-400">Full name</label>
                        <input id="new-name" name="name" required maxlength="80"
                               class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label for="new-email" class="mb-1 block text-xs font-medium text-slate-400">Email</label>
                        <input id="new-email" name="email" type="email" required
                               class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label for="new-role" class="mb-1 block text-xs font-medium text-slate-400">Role</label>
                        <select id="new-role" name="role" class="w-full rounded-lg border-0 bg-white/[0.05] px-3 py-2 text-sm text-white ring-1 ring-white/10 focus:ring-2 focus:ring-amber-500">
                            @foreach($roles as $value => $label)
                                <option value="{{ $value }}" @selected($value === 'support')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">
                        Create operator
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
