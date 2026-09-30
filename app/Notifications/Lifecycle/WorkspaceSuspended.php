<?php

namespace App\Notifications\Lifecycle;

use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * Sending has stopped.
 *
 * Written to be re-read a month later by someone deciding whether to come
 * back, so it says what survived and how to restart — not what went wrong.
 */
class WorkspaceSuspended extends LifecycleNotification
{
    protected function content(User $notifiable): LifecycleContent
    {
        return new LifecycleContent(
            subject: 'Sending is paused on your workspace',
            heading: 'Sending is paused',
            greetingName: $this->firstName($notifiable),
            lines: [
                'The unpaid invoice went past its grace window, so outgoing email is paused for now.',
                'Everything else is intact and stays that way: contacts, lists, campaigns, automations, relay configuration, and every report you have built. Settle the invoice and sending switches back on within seconds — there is nothing to set up again.',
            ],
            eyebrow: 'Paused',
            preheader: 'Your data is untouched. One payment restores sending.',
            actionLabel: 'Restore sending',
            actionUrl: route('billing.index'),
            outro: [
                'If the plan no longer fits, reply and tell us what would. Moving you down a tier is easier than losing you.',
            ],
            tone: 'danger',
        );
    }
}
