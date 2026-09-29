<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\Security\SecurityAlert;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Throwable;

/**
 * Turns Fortify's account-security events into a message the owner can act on.
 *
 * Disabling two-factor is the single most valuable thing an attacker can do
 * after taking a session: it removes the control that would have stopped
 * them re-entering later. It happened silently.
 */
class SendSecurityAlerts
{
    public function twoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->alert(
            $event->user,
            'Two-factor authentication was switched on',
            'Two-factor authentication is now active on your account. You will be asked for a code from your authenticator app the next time you sign in.',
            whenWrong: 'If this was not you, somebody else may control your account — reset your password immediately and contact support.',
        );
    }

    public function twoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->alert(
            $event->user,
            'Two-factor authentication was switched off',
            'Two-factor authentication has been removed from your account. Signing in now needs only your password.',
        );
    }

    public function recoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->alert(
            $event->user,
            'Your two-factor recovery codes were regenerated',
            'A new set of recovery codes was generated. Your previous codes no longer work.',
        );
    }

    /** @param list<string> $extra */
    private function alert(object $user, string $event, string $headline, array $extra = [], ?string $whenWrong = null): void
    {
        if (! $user instanceof User) {
            return;
        }

        try {
            $user->notify(new SecurityAlert($event, $headline, $extra, $whenWrong));
        } catch (Throwable $e) {
            // A security notice must never be able to break the action that
            // triggered it — a customer who cannot turn 2FA *on* because the
            // mailer is down is strictly worse off.
            report($e);
        }
    }
}
