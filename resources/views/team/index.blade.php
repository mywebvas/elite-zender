<x-layouts.app header="Team" title="Team">

    <div class="mx-auto max-w-4xl">

        {{-- Seat meter. The number on the pricing page has to mean something. --}}
        <section class="card mb-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Team members</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        @if($seats['limit'] === null)
                            Your plan includes unlimited seats.
                        @else
                            {{ $seats['used'] }} of {{ $seats['limit'] }} {{ Str::plural('seat', $seats['limit']) }} in use, counting pending invitations.
                        @endif
                    </p>
                </div>

                @if($seats['limit'] !== null && $seats['remaining'] === 0)
                    <a href="{{ route('billing.index') }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                        Add seats
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endif
            </div>

            @if($seats['limit'] !== null)
                @php $pct = $seats['limit'] > 0 ? min(100, (int) round($seats['used'] / $seats['limit'] * 100)) : 0; @endphp
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10"
                     role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"
                     aria-label="Seats in use">
                    <div @class([
                            'h-full rounded-full transition-all duration-500',
                            'bg-rose-500' => $pct >= 100,
                            'bg-amber-500' => $pct >= 80 && $pct < 100,
                            'bg-brand-600 dark:bg-brand-500' => $pct < 80,
                         ])
                         style="width: {{ $pct }}%"></div>
                </div>
            @endif
        </section>

        {{-- Invite --}}
        <section class="card mb-6 p-6">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Invite someone</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                They get an email with a link that is good for {{ \App\Models\Invitation::TTL_DAYS }} days.
                Because the link only ever reaches their inbox, an invited colleague can send straight away —
                no second verification step.
            </p>

            <form method="POST" action="{{ route('team.invite') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                @csrf

                <div class="flex-1">
                    <label for="invite-email" class="sr-only">Email address</label>
                    <input id="invite-email" name="email" type="email" required
                           value="{{ old('email') }}"
                           placeholder="colleague@company.com"
                           class="input @error('email') ring-rose-400 dark:ring-rose-500 @enderror">
                    @error('email')
                        <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:w-44">
                    <label for="invite-role" class="sr-only">Role</label>
                    <select id="invite-role" name="role" class="input">
                        @foreach($roles as $value => $label)
                            @continue($value === \App\Models\Role::OWNER && ! auth()->user()->isOwner())
                            <option value="{{ $value }}" @selected(old('role', \App\Models\Role::MEMBER) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role')
                        <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <x-button type="submit" variant="primary" class="sm:self-start">Send invitation</x-button>
            </form>

            <dl class="mt-5 grid gap-2 border-t border-slate-100 pt-4 text-xs text-slate-500 dark:border-white/[0.06] dark:text-slate-400 sm:grid-cols-2">
                <div><dt class="inline font-semibold text-slate-700 dark:text-slate-300">Owner</dt> — everything, including billing and ownership.</div>
                <div><dt class="inline font-semibold text-slate-700 dark:text-slate-300">Admin</dt> — everything except transferring ownership.</div>
                <div><dt class="inline font-semibold text-slate-700 dark:text-slate-300">Member</dt> — create and send campaigns; no relays or billing.</div>
                <div><dt class="inline font-semibold text-slate-700 dark:text-slate-300">Viewer</dt> — read-only.</div>
            </dl>
        </section>

        {{-- Pending invitations --}}
        @if($invitations->isNotEmpty())
            <section class="card mb-6 overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4 dark:border-white/[0.06]">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pending invitations</h3>
                </div>

                @foreach($invitations as $invitation)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4 last:border-0 dark:border-white/[0.06]">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $invitation->email }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ $roles[$invitation->role] ?? $invitation->role }} ·
                                @if($invitation->isExpired())
                                    <span class="font-semibold text-rose-600 dark:text-rose-400">expired {{ $invitation->expires_at->diffForHumans() }}</span>
                                @else
                                    expires {{ $invitation->expires_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>

                        <div class="flex flex-shrink-0 items-center gap-2">
                            <form method="POST" action="{{ route('team.invitations.resend', $invitation) }}">
                                @csrf
                                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-600 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-50 dark:text-slate-300 dark:ring-white/10 dark:hover:bg-white/[0.06]">
                                    Resend
                                </button>
                            </form>
                            <form method="POST" action="{{ route('team.invitations.revoke', $invitation) }}"
                                  onsubmit="return confirm('Revoke the invitation to {{ $invitation->email }}? The link in their email stops working immediately.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                                    Revoke
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- Members --}}
        <section class="card overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-4 dark:border-white/[0.06]">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Members</h3>
            </div>

            @foreach($members as $member)
                @php $isMe = $member->is(auth()->user()); @endphp

                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4 last:border-0 dark:border-white/[0.06]">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                            {{ Str::of($member->name)->substr(0, 1)->upper() }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                {{ $member->name }}
                                @if($isMe)<span class="ml-1 text-xs font-normal text-slate-400">(you)</span>@endif
                            </p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ $member->email }}
                                @unless($member->hasVerifiedEmail())
                                    <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">unverified</span>
                                @endunless
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-shrink-0 items-center gap-2">
                        @can('update', $member)
                            <form method="POST" action="{{ route('team.members.role', $member) }}" class="flex items-center gap-2">
                                @csrf @method('PUT')
                                <label for="role-{{ $member->id }}" class="sr-only">Role for {{ $member->name }}</label>
                                <select id="role-{{ $member->id }}" name="role" class="input !py-1.5 !text-xs !w-auto"
                                        onchange="this.form.requestSubmit()">
                                    @foreach($roles as $value => $label)
                                        @continue($value === \App\Models\Role::OWNER && ! auth()->user()->isOwner())
                                        <option value="{{ $value }}" @selected($member->role === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <noscript><button type="submit" class="text-xs font-semibold underline">Save</button></noscript>
                            </form>
                        @else
                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-white/[0.06] dark:text-slate-300">
                                {{ $roles[$member->role] ?? $member->role }}
                            </span>
                        @endcan

                        @can('remove', $member)
                            <form method="POST" action="{{ route('team.members.remove', $member) }}"
                                  onsubmit="return confirm('Remove {{ $member->name }}? Their sessions and API tokens are revoked immediately. Nothing they created is deleted.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                                    Remove
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </section>

    </div>

</x-layouts.app>
