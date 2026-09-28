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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($input) {
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
    }
}
