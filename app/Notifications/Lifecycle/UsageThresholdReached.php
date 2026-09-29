<?php

namespace App\Notifications\Lifecycle;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * "You are running out of allowance."
 *
 * The honest version of an upsell: a customer who hits their monthly cap
 * mid-campaign finds out when recipients stop receiving mail, which is the
 * worst possible moment and entirely our fault for not saying anything at
 * 80%. Warning early converts far better than blocking late, and it is the
 * difference between an upgrade and a complaint.
 */
class UsageThresholdReached extends LifecycleNotification
{
    public function __construct(
        private readonly Subscription $subscription,
        private readonly int $threshold,
        private readonly int $used,
        private readonly int $limit,
    ) {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $spent = $this->threshold >= 100;
        $resetsOn = now()->endOfMonth()->addDay()->toFormattedDayDateString();

        return new LifecycleContent(
            subject: $spent
                ? 'You have used your monthly sending allowance'
                : sprintf('You have used %d%% of this month\'s sending allowance', $this->threshold),
            heading: $spent ? 'Monthly allowance spent' : 'Approaching your monthly allowance',
            greetingName: $this->firstName($notifiable),
            lines: $spent
                ? [
                    sprintf(
                        'You have sent all %s emails included in the %s plan this month. Further sends are paused until the allowance resets on <strong>%s</strong>.',
                        number_format($this->limit),
                        $this->subscription->planName(),
                        $resetsOn,
                    ),
                    'Upgrading takes effect immediately and is prorated — you only pay for the rest of this period, and anything queued starts moving again straight away.',
                ]
                : [
                    sprintf(
                        'You have sent %s of the %s emails included in your %s plan this month — about %d%%.',
                        number_format($this->used),
                        number_format($this->limit),
                        $this->subscription->planName(),
                        $this->threshold,
                    ),
                    sprintf(
                        'Nothing is restricted yet. The allowance resets on <strong>%s</strong>; if you have a campaign planned before then, moving up a tier now avoids it stopping half-way through a send.',
                        $resetsOn,
                    ),
                ],
            eyebrow: 'Usage',
            preheader: $spent
                ? sprintf('Sending resumes on %s, or immediately on a higher plan.', $resetsOn)
                : sprintf('%s of %s sent this month.', number_format($this->used), number_format($this->limit)),
            facts: [
                'Sent this month' => number_format($this->used),
                'Included' => number_format($this->limit),
                'Resets' => $resetsOn,
            ],
            actionLabel: $spent ? 'Upgrade and resume sending' : 'Review plans',
            actionUrl: route('billing.index'),
            tone: $spent ? 'danger' : 'warning',
        );
    }
}
