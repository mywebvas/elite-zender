<?php

namespace App\Notifications\Lifecycle;

use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * The biggest leak in any SaaS funnel: signed up, never activated.
 *
 * A workspace that has not connected a relay has not experienced the product
 * at all — it cannot send, so it will never renew, and it will churn without
 * ever having been a customer in any meaningful sense. One welcome email and
 * then silence forever was leaving that entire cohort on the floor.
 *
 * Two nudges, both of which stop the instant the blocking step is done. The
 * copy names the *one* thing standing in the way rather than re-listing the
 * whole checklist, because a five-item list reads as work and one item reads
 * as a task.
 */
class ActivationNudge extends LifecycleNotification
{
    /**
     * @param  array{key: string, label: string, help: string, url: string, cta: string}  $step
     * @param  1|2  $nudge
     */
    public function __construct(
        private readonly array $step,
        private readonly int $nudge,
    ) {
        parent::__construct();
    }

    /** Guidance, not a contractual notice. */
    public function category(): string
    {
        return 'product';
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $first = $this->nudge === 1;

        return new LifecycleContent(
            subject: $first
                ? 'One step left before you can send'
                : 'Still stuck? We can set it up with you',
            heading: $first ? 'You are one step from sending' : 'Want a hand with this?',
            greetingName: $this->firstName($notifiable),
            lines: $first
                ? [
                    sprintf(
                        'Your workspace is ready apart from one thing: <strong>%s</strong>. %s',
                        e($this->step['label']),
                        e($this->step['help']),
                    ),
                    'It takes about two minutes, and everything downstream — campaigns, automations, reporting — starts working the moment it is done.',
                ]
                : [
                    sprintf(
                        'Your workspace is still waiting on one step: <strong>%s</strong>.',
                        e($this->step['label']),
                    ),
                    'If something is in the way — credentials you do not have, a provider you are unsure about, a list in an awkward format — reply to this email and tell us what you are looking at. We will either fix it or tell you straight that we are not the right tool.',
                    'No follow-ups after this one either way.',
                ],
            eyebrow: 'Getting started',
            preheader: sprintf('Next step: %s.', $this->step['label']),
            actionLabel: $this->step['cta'],
            actionUrl: $this->step['url'],
            outro: $first
                ? ['Already done it? Then ignore this — the reminder stops on its own.']
                : [],
            tone: $first ? 'brand' : 'warning',
        );
    }
}
