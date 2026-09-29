<?php

namespace App\Notifications\Lifecycle;

use App\Models\DataRequest;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * Erasure requested, and the clock started.
 *
 * Two jobs. Confirm the request unambiguously, because an unconfirmed
 * deletion is a support ticket from somebody who is not sure whether their
 * data is gone. And make the window — and the way out of it — impossible to
 * miss: a cooling-off period nobody knows about protects nobody.
 */
class WorkspaceDeletionScheduled extends LifecycleNotification
{
    public function __construct(private readonly DataRequest $request)
    {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $on = $this->request->scheduled_for;

        return new LifecycleContent(
            subject: 'Your workspace is scheduled for deletion',
            heading: 'Deletion scheduled',
            greetingName: $this->firstName($notifiable),
            lines: [
                sprintf(
                    'Your workspace and everything in it will be permanently deleted on <strong>%s</strong>. Contacts, campaigns, automations, reporting and every team member\'s access — all of it, irreversibly.',
                    $on?->toFormattedDayDateString() ?? 'the scheduled date',
                ),
                sprintf(
                    'Until then nothing changes and you can call it off with one click. After %s we cannot recover any of it, because that is what deletion means.',
                    $on?->toFormattedDayDateString() ?? 'that date',
                ),
                'If you want a copy first, request an export from the same page — it takes a couple of minutes and the deletion will wait.',
            ],
            eyebrow: 'Deletion',
            preheader: 'You can call this off until '.($on?->toFormattedDayDateString() ?? 'the scheduled date').'.',
            facts: array_filter([
                'Deletes on' => $on?->toFormattedDayDateString(),
                'Requested by' => $this->request->requester?->email,
            ]),
            actionLabel: 'Cancel the deletion',
            actionUrl: route('settings.index').'#data',
            outro: [
                'Suppression records are kept as one-way hashes even after deletion. They contain no addresses, and they exist so that anyone who unsubscribed stays unsubscribed — which is a promise we made to them, not to you.',
            ],
            tone: 'danger',
        );
    }
}
