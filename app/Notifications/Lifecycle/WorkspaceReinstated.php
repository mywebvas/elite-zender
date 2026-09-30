<?php

namespace App\Notifications\Lifecycle;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * Back online.
 *
 * Short and unambiguous. Somebody who has just paid to end an outage wants
 * one fact confirmed — that it is over — and does not want to log in to
 * check.
 */
class WorkspaceReinstated extends LifecycleNotification
{
    public function __construct(private readonly Subscription $subscription)
    {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        return new LifecycleContent(
            subject: 'You are back — sending is live again',
            heading: 'Sending is live again',
            greetingName: $this->firstName($notifiable),
            lines: [
                sprintf(
                    'Your payment landed and the %s plan is active again. Sending resumed immediately, and anything queued while you were paused will go out on the next worker pass.',
                    $this->subscription->planName(),
                ),
                'Worth thirty seconds: saving a card means a renewal can never do this again. You can remove it whenever you like.',
            ],
            eyebrow: 'Restored',
            preheader: 'Your plan is active and sending has resumed.',
            facts: array_filter([
                'Plan' => $this->subscription->planName(),
                'Next renewal' => $this->subscription->current_period_end?->toFormattedDayDateString(),
            ]),
            actionLabel: 'Back to your dashboard',
            actionUrl: route('dashboard'),
            tone: 'success',
        );
    }
}
