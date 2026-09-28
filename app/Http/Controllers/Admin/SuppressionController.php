<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use App\Models\SuppressionEntry;
use App\Models\Tenant;
use App\Platform\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The global suppression list.
 *
 * Addresses are stored as a keyed hash so opt-outs survive GDPR erasure, which
 * means this page can confirm whether a given address is suppressed but can
 * never list them. That is the correct trade, and the reason the lookup is a
 * search box rather than a table.
 */
class SuppressionController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): View
    {
        $email = trim((string) $request->query('email', ''));
        $match = null;

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $match = SuppressionEntry::query()
                ->where('email_hash', SuppressionEntry::hash($email))
                ->get();
        }

        return view('admin.suppressions.index', [
            'email' => $email,
            'match' => $match,
            'counts' => SuppressionEntry::query()
                ->selectRaw('reason, count(*) as aggregate')
                ->groupBy('reason')
                ->pluck('aggregate', 'reason'),
            'total' => SuppressionEntry::query()->count(),
            'recent' => SuppressionEntry::query()->latest('created_at')->limit(15)->get(),
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Add an address by hand — usually after a complaint arrives out of band. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // A nullable field the form did not submit has no key at all, so this
        // must be read defensively rather than indexed.
        $tenantId = $validated['tenant_id'] ?? null;

        SuppressionEntry::suppress(
            $validated['email'],
            SuppressionEntry::REASON_MANUAL,
            $tenantId,
            $validated['reason'] ?? null,
        );

        $this->activity->record(
            action: 'suppression.add',
            description: 'Manually suppressed an address'
                .($tenantId === null ? ' platform-wide' : ' for one workspace'),
            tenantId: $tenantId,
            severity: AdminActivity::SEVERITY_NOTICE,
            reason: $validated['reason'] ?? null,
        );

        return back()->with('success', 'Address suppressed. No workspace will be able to mail it.');
    }

    /**
     * Remove a suppression.
     *
     * Deliberately requires a reason: un-suppressing an address that hard
     * bounced or complained is how a sending domain gets blocklisted, so the
     * decision should be attributable.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $entry = SuppressionEntry::findOrFail($id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $wasReason = $entry->reason;
        $entry->delete();

        $this->activity->record(
            action: 'suppression.remove',
            description: "Removed a {$wasReason} suppression",
            tenantId: $entry->tenant_id,
            severity: AdminActivity::SEVERITY_CRITICAL,
            reason: $validated['reason'],
        );

        return back()->with('success', 'Suppression removed. This address can be mailed again.');
    }
}
