<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use App\Models\ApiKey;
use App\Platform\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform API keys.
 *
 * Scoped, expiring, IP-restrictable and revocable. The plaintext is shown
 * exactly once, on creation — there is no "reveal" action, because a key you
 * can re-read from the UI is a key that leaks from a screen-share.
 */
class ApiKeyController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(): View
    {
        return view('admin.api-keys.index', [
            'keys' => ApiKey::with('creator:id,name')->latest()->get(),
            'abilities' => ApiKey::ABILITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(array_keys(ApiKey::ABILITIES))],
            'allowed_ips' => ['nullable', 'string', 'max:500'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $ips = collect(explode(',', (string) ($validated['allowed_ips'] ?? '')))
            ->map(fn (string $ip) => trim($ip))
            ->filter(fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false)
            ->values()
            ->all();

        [$key, $plain] = ApiKey::mint(
            name: $validated['name'],
            abilities: $validated['abilities'],
            createdBy: auth('admin')->id(),
            allowedIps: $ips,
            expiresAt: isset($validated['expires_in_days'])
                ? now()->addDays((int) $validated['expires_in_days'])
                : null,
        );

        $this->activity->record(
            action: 'api_key.create',
            description: "Created API key “{$key->name}”",
            subject: $key,
            severity: AdminActivity::SEVERITY_CRITICAL,
            changes: ['abilities' => $key->abilities, 'expires_at' => $key->expires_at?->toDateString()],
        );

        // Flashed, not persisted: this is the only time it exists in the clear.
        return back()->with('new_api_key', $plain)->with('success', 'API key created. Copy it now — it will not be shown again.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $key = ApiKey::findOrFail($id);

        abort_if($key->revoked_at !== null, 422, 'That key is already revoked.');

        $key->revoke();

        $this->activity->record(
            action: 'api_key.revoke',
            description: "Revoked API key “{$key->name}”",
            subject: $key,
            severity: AdminActivity::SEVERITY_CRITICAL,
            reason: $request->string('reason')->toString() ?: null,
        );

        return back()->with('success', "“{$key->name}” revoked. Any request using it now fails.");
    }
}
