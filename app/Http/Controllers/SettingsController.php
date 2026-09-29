<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWorkspaceSettingsRequest;
use App\Tenancy\TenantContext;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $tenant = TenantContext::tenant();

        return view('settings.index', [
            'tenant' => $tenant,
            'timezones' => DateTimeZone::listIdentifiers(),
            'exports' => $tenant === null ? collect() : \App\Models\DataRequest::withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->where('type', \App\Models\DataRequest::TYPE_EXPORT)
                ->latest()
                ->limit(3)
                ->get(),
            'pendingDeletion' => $tenant === null ? null : \App\Models\DataRequest::withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->where('type', \App\Models\DataRequest::TYPE_DELETION)
                ->where('status', \App\Models\DataRequest::STATUS_PENDING)
                ->first(),
        ]);
    }

    /**
     * Persist workspace settings.
     *
     * The previous handler validated the payload and then threw it away while
     * flashing "Settings saved successfully" — the worst possible outcome for
     * a settings screen.
     */
    public function update(UpdateWorkspaceSettingsRequest $request): RedirectResponse
    {
        $tenant = TenantContext::tenant();

        abort_if($tenant === null, 403);

        $validated = $request->validated();

        $tenant->forceFill([
            'name' => $validated['workspace_name'] ?? $tenant->name,
            'settings' => array_merge($tenant->settings ?? [], array_filter([
                'timezone' => $validated['timezone'] ?? null,
                'reply_to' => $validated['reply_to'] ?? null,
            ], static fn ($value) => $value !== null)),
        ])->save();

        return back()->with('success', 'Settings saved successfully.');
    }

    /**
     * Bounce mailbox (IMAP).
     *
     * `ScanBounces` runs every fifteen minutes and skips any workspace whose
     * `settings.imap` is not an array — which was every workspace, because
     * the form that claimed to save this had no action and a button that
     * fired a success toast. The whole Bounce Shield feature was unreachable
     * while telling customers it was configured.
     *
     * Only owners and admins: an IMAP credential reads a mailbox.
     */
    public function updateImap(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRoleAtLeast(\App\Models\Role::ADMIN) ?? false, 403);

        $tenant = TenantContext::tenant();

        abort_if($tenant === null, 403);

        $existing = is_array($tenant->setting('imap')) ? $tenant->setting('imap') : [];

        if ($request->boolean('disconnect')) {
            $tenant->forceFill([
                'settings' => \Illuminate\Support\Arr::except($tenant->settings ?? [], 'imap'),
            ])->save();

            return back()->with('success', 'Bounce mailbox disconnected. Automatic suppression is off.');
        }

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            // Blank means "leave it alone", never "erase it" — the field
            // renders masked, so it always posts empty on an edit.
            'password' => [$existing === [] ? 'required' : 'nullable', 'string', 'max:500'],
            'encryption' => ['required', 'string', \Illuminate\Validation\Rule::in(['ssl', 'none'])],
        ]);

        $password = filled($validated['password'] ?? null)
            ? \Illuminate\Support\Facades\Crypt::encryptString($validated['password'])
            : ($existing['password'] ?? null);

        $tenant->forceFill([
            'settings' => array_merge($tenant->settings ?? [], [
                'imap' => [
                    'host' => $validated['host'],
                    'port' => $validated['port'],
                    'username' => $validated['username'],
                    'password' => $password,
                    'encryption' => $validated['encryption'],
                ],
            ]),
        ])->save();

        return back()->with('success', 'Bounce mailbox saved. We will scan it within fifteen minutes.');
    }

    /**
     * Per-user mail preferences.
     *
     * Only the optional categories are writable. Billing and security notices
     * are absent by construction rather than by a check here — a product that
     * sends email on other people's behalf has no business ignoring an
     * unsubscribe on its own, but it also cannot let somebody opt out of the
     * warning that precedes a suspension.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $optional = array_keys(\App\Models\User::OPTIONAL_NOTIFICATIONS);

        $request->validate(array_merge(...array_map(
            static fn (string $key) => [$key => ['nullable', 'boolean']],
            $optional,
        )) ?: []);

        $preferences = [];

        foreach ($optional as $key) {
            $preferences[$key] = $request->boolean($key);
        }

        $user->forceFill(['notification_preferences' => $preferences])->save();

        return back()->with('success', 'Email preferences updated.');
    }
}
