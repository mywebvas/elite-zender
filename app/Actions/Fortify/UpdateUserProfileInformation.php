<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\Security\SecurityAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Throwable;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ])->validateWithBag('updateProfileInformation');

        // `User` implements MustVerifyEmail unconditionally now, so changing
        // the address always re-opens verification — which is the point: an
        // address nobody has confirmed must not inherit a confirmed one's
        // right to send.
        if ($input['email'] !== $user->email) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $previous = (string) $user->email;

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();

        $this->warnPreviousAddress($previous, $user);
    }

    /**
     * Tell the address that is being taken away.
     *
     * This is the control that catches account takeover. Somebody with a
     * session — a stolen cookie, an unlocked laptop — changes the email and
     * then runs a password reset to the address they now own. Every step is
     * a legitimate action by an authenticated user, so nothing else objects.
     * The only signal the real owner ever gets is a message to the old
     * address, which is why it goes there and not only to the new one.
     */
    private function warnPreviousAddress(string $previous, User $user): void
    {
        if ($previous === '' || $previous === $user->email) {
            return;
        }

        try {
            Notification::route('mail', $previous)->notify(new SecurityAlert(
                'The email address on your account was changed',
                sprintf(
                    'The address for this account was changed from <strong>%s</strong> to <strong>%s</strong>. Sign-in and password resets now go to the new address.',
                    e($previous),
                    e((string) $user->email),
                ),
                whenWrong: sprintf(
                    '<strong>If you did not do this, act now.</strong> You can no longer reset the password yourself, so write to %s from this address immediately and we will lock the account.',
                    e((string) config('platform.support_email')),
                ),
            ));
        } catch (Throwable $e) {
            // Never block a legitimate profile update on a mail failure.
            report($e);
        }
    }
}
