<?php

namespace App\Http\Controllers;

use App\Jobs\BuildWorkspaceExportJob;
use App\Lifecycle\LifecycleMessenger;
use App\Models\DataRequest;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Lifecycle\WorkspaceDeletionScheduled;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Right of access and right to erasure, self-service.
 *
 * GDPR Articles 15 and 17 give a customer a copy of their data and the right
 * to have it erased. Both were an email to an operator, which is not a right
 * — it is a favour, granted at whatever speed somebody gets round to it.
 *
 * This is also the last stage of the lifecycle, and the one every other
 * stage is judged against: a product that makes leaving hard is a product
 * people are wary of joining.
 */
class DataRequestController extends Controller
{
    /** Queue a full copy of the workspace. */
    public function export(): RedirectResponse
    {
        $this->authorizeOwnership();

        $tenant = $this->tenant();

        // One build at a time. Exports are expensive and a customer clicking
        // twice wants one archive, not two.
        $pending = DataRequest::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', DataRequest::TYPE_EXPORT)
            ->where('status', DataRequest::STATUS_PENDING)
            ->exists();

        if ($pending) {
            return back()->with('success', 'An export is already being prepared — we will email you the moment it is ready.');
        }

        $request = DataRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'requested_by' => auth()->id(),
            'type' => DataRequest::TYPE_EXPORT,
            'status' => DataRequest::STATUS_PENDING,
        ]);

        BuildWorkspaceExportJob::dispatch((string) $request->getKey());

        return back()->with('success', 'Building your export. We will email you when it is ready to download.');
    }

    /**
     * Stream a finished export.
     *
     * Behind the session and the policy, never a signed public URL: the file
     * is the customer's entire contact list, and a link that works without
     * logging in is a link that works for whoever finds the email.
     */
    public function download(string $id): StreamedResponse
    {
        $this->authorizeOwnership();

        $request = $this->findOwned($id);

        abort_unless($request->isDownloadable(), 404);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        abort_unless($disk->exists((string) $request->file_path), 404);

        return $disk->download(
            (string) $request->file_path,
            sprintf('%s-export-%s.zip', str($this->tenant()->name)->slug(), $request->created_at?->format('Y-m-d')),
        );
    }

    /** Schedule irreversible deletion, after a cooling-off window. */
    public function requestDeletion(Request $request): RedirectResponse
    {
        $this->authorizeOwnership(ownerOnly: true);

        $tenant = $this->tenant();

        $validated = $request->validate([
            // Re-authentication, because this is the one action that cannot
            // be undone and a walk-up attacker on an unlocked laptop must
            // not be able to do it.
            'password' => ['required', 'current_password:web'],
            'confirmation' => ['required', 'string', Rule::in([$tenant->name])],
            'reason' => ['nullable', 'string', Rule::in(array_keys(Subscription::CANCELLATION_REASONS))],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'password.current_password' => 'That password is not correct.',
            'confirmation.in' => 'Type the workspace name exactly to confirm.',
        ]);

        $existing = DataRequest::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', DataRequest::TYPE_DELETION)
            ->where('status', DataRequest::STATUS_PENDING)
            ->first();

        if ($existing !== null) {
            return back()->withErrors(['confirmation' => 'This workspace is already scheduled for deletion.']);
        }

        $deletion = DataRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'requested_by' => auth()->id(),
            'type' => DataRequest::TYPE_DELETION,
            'status' => DataRequest::STATUS_PENDING,
            'reason' => $validated['reason'] ?? null,
            'note' => $validated['note'] ?? null,
            'scheduled_for' => now()->addDays(DataRequest::DELETION_GRACE_DAYS),
        ]);

        // Everyone with access is told, not only the person who clicked.
        app(LifecycleMessenger::class)->sendOnce(
            $tenant,
            'deletion_scheduled:'.$deletion->getKey(),
            fn () => new WorkspaceDeletionScheduled($deletion->load('requester')),
        );

        return back()->with('success', sprintf(
            'Your workspace will be deleted on %s. You can call it off any time before then.',
            $deletion->scheduled_for?->toFormattedDayDateString() ?? 'the scheduled date',
        ));
    }

    /** Call off a scheduled deletion — the whole point of the window. */
    public function cancelDeletion(string $id): RedirectResponse
    {
        $this->authorizeOwnership(ownerOnly: true);

        $deletion = $this->findOwned($id);

        abort_unless($deletion->isCancellable(), 404);

        $deletion->forceFill(['status' => DataRequest::STATUS_CANCELLED])->save();

        return back()->with('success', 'Deletion cancelled. Nothing was removed.');
    }

    private function findOwned(string $id): DataRequest
    {
        return DataRequest::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant()->getKey())
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * Exports expose everything; deletion destroys everything. Neither is a
     * member-level action.
     */
    private function authorizeOwnership(bool $ownerOnly = false): void
    {
        /** @var User|null $user */
        $user = auth('web')->user();

        abort_if($user === null, 403);
        abort_unless($ownerOnly ? $user->isOwner() : $user->hasRoleAtLeast(Role::ADMIN), 403);
    }

    private function tenant(): Tenant
    {
        $tenant = TenantContext::tenant();

        abort_if($tenant === null, 403);

        return $tenant;
    }
}
