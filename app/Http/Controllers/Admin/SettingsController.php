<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use App\Platform\ActivityLogger;
use App\Platform\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Platform configuration.
 *
 * Everything here was previously env-only, which meant rotating a leaked
 * Stripe key or closing signups during an incident required a deploy. That is
 * the wrong response time for both.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.index', [
            'schema' => Settings::schema(),
            'groups' => Settings::groups(),
            'values' => $this->settings->forDisplay(),
            'gateways' => app(\App\Billing\PaymentGatewayManager::class)->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $schema = Settings::schema();

        $rules = [];

        foreach ($schema as $key => $meta) {
            // Dots are path separators in validation, so the form posts a
            // flat map keyed by index and we rebuild it below.
            $rules['settings.'.str_replace('.', '__', $key)] = match ($meta['type']) {
                'bool' => ['nullable', 'boolean'],
                'int' => ['nullable', 'integer', 'min:0', 'max:3650'],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        $validated = $request->validate($rules);

        $values = [];

        foreach ($schema as $key => $meta) {
            $field = str_replace('.', '__', $key);

            $values[$key] = $meta['type'] === 'bool'
                ? $request->boolean('settings.'.$field)
                : ($validated['settings'][$field] ?? null);
        }

        $this->settings->put($values);

        // The values themselves are not logged — Settings marks secrets, and
        // an audit row that leaks the key it recorded is worse than no row.
        $this->activity->record(
            action: 'settings.update',
            description: 'Updated platform settings',
            severity: AdminActivity::SEVERITY_CRITICAL,
            changes: ['fields' => array_keys(array_filter($values, static fn ($v) => $v !== null && $v !== ''))],
        );

        return back()->with('success', 'Settings saved. Changes are live immediately.');
    }

    /** Remove one override so the compiled config takes over again. */
    public function reset(Request $request): RedirectResponse
    {
        $key = (string) $request->input('key');

        abort_unless(array_key_exists($key, Settings::schema()), 422);

        $this->settings->forget($key);

        $this->activity->record(
            action: 'settings.reset',
            description: "Reset “{$key}” to its deployed default",
            severity: AdminActivity::SEVERITY_NOTICE,
        );

        return back()->with('success', 'Reset to the deployed default.');
    }
}
