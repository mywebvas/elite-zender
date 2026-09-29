<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\Security\SecurityAlert;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Throwable;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        try {
            // A completed reset deserves the same confirmation as a change:
            // if somebody else triggered it, this is the owner's signal.
            $user->notify(new SecurityAlert(
                'Your password was reset',
                'Your password was just reset using a link sent to your email address.',
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
