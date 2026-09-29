<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = \Illuminate\Support\Facades\DB::transaction(function () use ($input) {
            $workspaceName = explode(' ', $input['name'])[0]."'s Workspace";
            $tenant = \App\Models\Tenant::create([
                'name' => $workspaceName,
                'slug' => \Illuminate\Support\Str::slug($workspaceName).'-'.uniqid(),
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'role' => \App\Models\Role::OWNER,
            ]);

            // Inside the same transaction: a workspace without a subscription
            // row would fall back to free-tier limits with no way to upgrade
            // from the billing page.
            app(\App\Billing\BillingService::class)->startTrial($tenant);

            return $user;
        });

        // Deliberately after the commit. A welcome email queued inside the
        // transaction is a welcome email sent for a workspace that may not
        // exist a millisecond later — and on the sync driver it would send
        // before the row it talks about is visible to anyone else.
        $this->welcome($user);

        return $user;
    }

    /**
     * The single most valuable email this product sends.
     *
     * Week-two retention in a sending tool tracks almost entirely with
     * whether a relay was connected on day one, so this has exactly one job:
     * get the new owner to the setup checklist. It must never be able to
     * break registration, hence the guard.
     */
    private function welcome(User $user): void
    {
        $tenant = $user->tenant;

        if ($tenant === null) {
            return;
        }

        app(\App\Lifecycle\LifecycleMessenger::class)->sendOnce(
            $tenant,
            'welcome:'.$tenant->getKey(),
            fn () => new \App\Notifications\Lifecycle\WorkspaceWelcome(
                app(\App\Billing\PlanGate::class)->subscriptionFor($tenant),
            ),
        );
    }
}
