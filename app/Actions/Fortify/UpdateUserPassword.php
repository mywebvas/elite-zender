<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\Security\SecurityAlert;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Throwable;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        $this->alert($user);
    }

    /**
     * Confirm the change to the account's own inbox.
     *
     * A password change is the moment an account stops belonging to whoever
     * used to have the old one. If the owner did it, the message is
     * reassurance; if they did not, it is the only warning they will get
     * before they are locked out.
     */
    private function alert(User $user): void
    {
        try {
            $user->notify(new SecurityAlert(
                'Your password was changed',
                'The password on your account was just changed. You will need the new one next time you sign in.',
            ));
        } catch (Throwable $e) {
            // Never fail a successful password change on a mail problem.
            report($e);
        }
    }
}
