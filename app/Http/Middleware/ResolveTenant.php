<?php

namespace App\Http\Middleware;

use App\Billing\PlanGate;
use App\Models\Tenant;
use App\Tenancy\TenantCache;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's tenant for the request: Eloquent global
 * scopes + cache key namespace. Queue jobs use TenantContext::run().
 */
class ResolveTenant
{
    /**
     * Publish the workspace's billing state to every view.
     *
     * One resolution per request, shared — otherwise each page re-queries the
     * subscription to decide whether to show a banner, and they drift.
     */
    private function shareBillingState(?Tenant $tenant): void
    {
        if ($tenant === null) {
            \Illuminate\Support\Facades\View::share('billingNotice', null);

            return;
        }

        \Illuminate\Support\Facades\View::share('billingNotice', $this->notice($tenant));
    }

    /**
     * The one thing this workspace most needs to know, right now.
     *
     * Ordered by cost of ignoring it: something is broken, something will
     * break, something needs finishing, something is ending. Only ever one
     * banner — stacking them is how people learn to scroll past all of them.
     *
     * Each notice carries its own call to action. A banner that says "we
     * could not collect your payment" and then sends you to a generic
     * billing page is a banner that loses half the people who read it; the
     * unpaid invoice gets linked directly, which is the entire mechanic
     * behind abandoned-checkout recovery.
     *
     * @return array{tone: string, message: string, action: array{label: string, url: string}}|null
     */
    private function notice(Tenant $tenant): ?array
    {
        $planGate = app(PlanGate::class);

        // Unverified beats everything: it is the only state where the send
        // button is dead for a reason the customer can fix in ten seconds.
        if ($planGate->awaitingEmailVerification($tenant)) {
            return [
                'tone' => 'warning',
                'message' => 'Confirm your email address to unlock sending. Everything else is already available.',
                'action' => ['label' => 'Confirm email', 'url' => route('verification.notice')],
            ];
        }

        $subscription = $planGate->subscriptionFor($tenant);

        if ($subscription === null) {
            return null;
        }

        $openInvoice = \App\Models\Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', \App\Models\Invoice::STATUS_OPEN)
            ->latest()
            ->first();

        $payAction = $openInvoice !== null
            ? ['label' => 'Finish payment', 'url' => route('billing.invoices.show', $openInvoice->getKey())]
            : ['label' => 'Go to billing', 'url' => route('billing.index')];

        $billing = ['label' => 'Go to billing', 'url' => route('billing.index')];

        return match (true) {
            $subscription->status === \App\Models\Subscription::STATUS_EXPIRED => [
                'tone' => 'danger',
                'message' => 'Your subscription has lapsed and sending is paused. Your data is untouched — settle the open invoice to pick up where you left off.',
                'action' => $payAction,
            ],

            $subscription->status === \App\Models\Subscription::STATUS_PAST_DUE => [
                'tone' => 'warning',
                'message' => 'We could not collect your last payment. Sending continues for now; please settle the open invoice to avoid interruption.',
                'action' => $payAction,
            ],

            // An invoice left open on an otherwise healthy account is almost
            // always an interrupted checkout, not a refusal to pay.
            $openInvoice !== null && $openInvoice->balance() > 0 => [
                'tone' => 'info',
                'message' => sprintf(
                    'Invoice %s is waiting to be paid. Picking up where you left off takes one click.',
                    $openInvoice->number,
                ),
                'action' => $payAction,
            ],

            $subscription->isEnding() => [
                'tone' => 'info',
                'message' => sprintf(
                    'Your plan ends on %s. You can undo this any time before then.',
                    $subscription->cancel_at?->toFormattedDayDateString() ?? 'the end of the period',
                ),
                'action' => ['label' => 'Keep my plan', 'url' => route('billing.index')],
            ],

            $subscription->onTrial() && $subscription->trial_ends_at->diffInDays(now()) >= -3 => [
                'tone' => 'info',
                'message' => sprintf('Your trial ends %s. Add a payment method to keep everything running.', $subscription->trial_ends_at->diffForHumans()),
                'action' => ['label' => 'Choose a plan', 'url' => route('billing.index')],
            ],

            default => null,
        };
    }

    /**
     * The workspace member this request is acting as, whichever guard proved it.
     *
     * Never the ambient default guard: admin routes share this middleware
     * stack, and an operator on the `admin` guard has no tenant_id — asking
     * "the" user would hand this an Admin and blow up mid-request.
     *
     * Both tenant-facing guards have to be consulted, in this order:
     *
     *  - `web`     — the session cookie the browser app uses.
     *  - `sanctum` — Bearer tokens from external API clients. This one was
     *    missing, and the consequence was not subtle: a token request has no
     *    session, so `auth('web')` returned null, no tenant was bound, and
     *    the `HasTenant` global scope silently became inert. Every list
     *    endpoint under /api/v1 then returned *every workspace's* rows —
     *    contacts, campaigns, lists and SMTP relay hostnames included.
     *
     * The result is type-checked: Sanctum can authenticate any model that
     * issues tokens, and only a User carries a tenant.
     */
    private function actingUser(): ?\App\Models\User
    {
        foreach (['web', 'sanctum'] as $guard) {
            $user = auth($guard)->user();

            if ($user instanceof \App\Models\User) {
                return $user;
            }
        }

        return null;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // The operator console is cross-tenant by design and must never be
        // scoped to (or blocked by) whatever customer session happens to be
        // open in the same browser.
        if ($request->is('admin', 'admin/*')) {
            return $next($request);
        }

        $user = $this->actingUser();
        $tenant = null;

        if ($user !== null && $user->tenant_id !== null) {
            $tenant = Tenant::find($user->tenant_id);

            if ($tenant === null) {
                // The account points at a workspace that no longer exists.
                // Continuing would run every query unscoped, so refuse.
                Log::warning('ResolveTenant: user references a missing tenant', [
                    'user_id' => $user->getKey(),
                    'tenant_id' => $user->tenant_id,
                ]);

                abort(403, 'Your workspace is unavailable.');
            }

            // A suspended workspace is closed to its own users, but an
            // operator must still be able to get in and out of it — otherwise
            // suspending a workspace while impersonating strands them there
            // with no route back to the console.
            if ($tenant->status !== Tenant::STATUS_ACTIVE && ! auth('admin')->check()) {
                // API clients get the machine answer; a browser gets a page
                // that explains itself. The person reading the HTML version is
                // a customer locked out of their own data, and a bare 403
                // tells them neither that it still exists nor who to ask.
                abort($request->expectsJson() || $request->is('api/*')
                    ? response()->json([
                        'error' => [
                            'code' => 'WORKSPACE_SUSPENDED',
                            'message' => 'This workspace has been suspended.',
                        ],
                    ], 403)
                    : response()->view('errors.workspace-suspended', [
                        'workspace' => $tenant->name,
                        'supportEmail' => config('platform.support_email'),
                    ], 403));
            }
        }

        return TenantContext::run($tenant, fn () => TenantCache::withNamespace($tenant, function () use ($next, $request, $tenant) {
            $this->shareBillingState($tenant);

            return $next($request);
        }));
    }
}
