<?php

namespace App\Notifications\Lifecycle;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * One check-in after a workspace drops to the free plan. Exactly one.
 *
 * A week is long enough that the decision has been lived with and short
 * enough that the data is still current. It asks a question rather than
 * making a pitch, because the reply is worth more than the reactivation:
 * churn you cannot attribute is churn you cannot fix.
 */
class WinBackOffer extends LifecycleNotification
{
    public function __construct(private readonly Subscription $subscription)
    {
        parent::__construct();
    }

    /** A check-in, not a service message: opt-out-able. */
    public function category(): string
    {
        return 'product';
    }

    protected function content(User $notifiable): LifecycleContent
    {
        return new LifecycleContent(
            subject: 'Anything we could have done differently?',
            heading: 'How did we do?',
            greetingName: $this->firstName($notifiable),
            lines: [
                'Your workspace moved to the free plan a week ago. Everything is still there — contacts, campaigns, automations, reporting — and it stays there whether or not you come back.',
                'If something was missing, too expensive, or simply did not work the way you needed, replying to this email is the fastest route to getting it looked at. It goes to the people who build the thing.',
                'And if it was just a quiet month: upgrading again takes one click and picks up exactly where you left off.',
            ],
            eyebrow: 'Checking in',
            preheader: 'Your workspace is intact. We would genuinely like to know what was missing.',
            facts: [
                'Now on' => $this->subscription->planName('Free'),
                'Everything kept' => 'Contacts, campaigns, automations, reporting',
            ],
            actionLabel: 'See your plans',
            actionUrl: route('billing.index'),
            tone: 'brand',
        );
    }
}
