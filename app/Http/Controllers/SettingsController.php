<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWorkspaceSettingsRequest;
use App\Tenancy\TenantContext;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.index', [
            'tenant' => TenantContext::tenant(),
            'timezones' => DateTimeZone::listIdentifiers(),
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
}
