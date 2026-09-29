<?php

namespace App\Notifications\Lifecycle;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * First contact.
 *
 * Deliberately one job, not five: get the new owner to connect a relay. A
 * workspace that never sends never converts, and the single strongest
 * predictor of week-two retention in a sending tool is whether a relay was
 * connected on day one.
 */
class WorkspaceWelcome extends LifecycleNotification
{
    public function __construct(private readonly ?Subscription $subscription = null)
    {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $trialEnds = $this->subscription?->trial_ends_at;

        $lines = [
            'Your workspace is live. Everything is already set up except the one thing only you can provide: the SMTP relay you want to send through.',
            'Connect a relay and you can send a real campaign in about four minutes. We will rotate across every relay you add, watch their health, and back off automatically before a provider starts throttling you.',
        ];

        if ($trialEnds !== null) {
            $lines[] = sprintf(
                'You are on a free trial until <strong>%s</strong>. No card is needed until then, and nothing is sent to a card we do not have.',
                $trialEnds->toFormattedDayDateString(),
            );
        }

        return new LifecycleContent(
            subject: 'Welcome to '.config('platform.name').' — start here',
            heading: 'Your workspace is ready',
            greetingName: $this->firstName($notifiable),
            lines: $lines,
            eyebrow: 'Getting started',
            preheader: 'Connect your first SMTP relay and send a real campaign in about four minutes.',
            actionLabel: 'Open the setup checklist',
            actionUrl: route('onboarding'),
            outro: [
                'Reply to this email if anything is unclear — it reaches a person, not a ticket queue.',
            ],
        );
    }
}
