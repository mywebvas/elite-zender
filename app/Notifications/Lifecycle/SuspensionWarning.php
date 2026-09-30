<?php

namespace App\Notifications\Lifecycle;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * The last email before sending stops.
 *
 * A suspension nobody saw coming is how a recoverable billing problem turns
 * into a cancellation and a bad review. This one states the exact date, says
 * precisely what will and will not happen, and makes clear that nothing is
 * deleted — because the fear that drives an angry support ticket is always
 * "have I lost my lists?".
 */
class SuspensionWarning extends LifecycleNotification
{
    public function __construct(
        private readonly Subscription $subscription,
        private readonly \Illuminate\Support\Carbon $pausesAt,
    ) {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        return new LifecycleContent(
            subject: sprintf('Sending pauses %s unless we hear from you', $this->pausesAt->diffForHumans()),
            heading: 'Your sending is about to pause',
            greetingName: $this->firstName($notifiable),
            lines: [
                sprintf(
                    'There is still an unpaid invoice on your workspace. On <strong>%s</strong> we will pause outgoing email until it is settled.',
                    $this->pausesAt->toFormattedDayDateString(),
                ),
                '<strong>Nothing is deleted.</strong> Your contacts, campaigns, automations, relay settings and reporting all stay exactly as they are. Pay whenever you are ready and sending resumes immediately — no re-setup, no re-import, no new account.',
            ],
            eyebrow: 'Before anything stops',
            preheader: 'Nothing is deleted. Sending resumes the moment the invoice is settled.',
            facts: [
                'Plan' => $this->subscription->planName(),
                'Sending pauses' => $this->pausesAt->toFormattedDayDateString(),
            ],
            actionLabel: 'Settle the invoice',
            actionUrl: route('billing.index'),
            outro: [
                'If the timing is genuinely bad, reply and ask — we would rather extend the window than lose you over a fortnight.',
            ],
            tone: 'danger',
        );
    }
}
