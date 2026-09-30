<?php

namespace App\Notifications\Lifecycle;

use App\Lifecycle\Money;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * The conversion moment.
 *
 * Sent a few days out, never on the day: someone who has to talk to finance
 * needs notice, and a trial that ends without warning converts to a support
 * ticket rather than a subscription. The message leads with what they have
 * already built, because that — not the price — is what they are deciding
 * whether to keep.
 */
class TrialEnding extends LifecycleNotification
{
    /** @param array<string, int> $usage */
    public function __construct(
        private readonly Subscription $subscription,
        private readonly array $usage = [],
    ) {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $endsAt = $this->subscription->trial_ends_at;
        $plan = $this->subscription->plan;
        $price = $plan?->priceFor($this->subscription->currency) ?? 0;

        $facts = [
            'Trial ends' => $endsAt?->toFormattedDayDateString() ?? 'shortly',
            'Plan' => $this->subscription->planName('Free'),
        ];

        if ($price > 0) {
            $facts['Then'] = Money::format($price, $this->subscription->currency).' / month';
        }

        foreach (['Contacts' => 'contacts', 'Emails sent' => 'emails_per_month'] as $label => $key) {
            if (($this->usage[$key] ?? 0) > 0) {
                $facts[$label] = number_format($this->usage[$key]);
            }
        }

        return new LifecycleContent(
            subject: sprintf('Your trial ends %s', $endsAt?->diffForHumans() ?? 'soon'),
            heading: 'Keep your workspace running',
            greetingName: $this->firstName($notifiable),
            lines: [
                sprintf(
                    'Your free trial ends on <strong>%s</strong>. Choose a plan before then and nothing changes — same relays, same lists, same history.',
                    $endsAt?->toFormattedDayDateString() ?? 'that date',
                ),
                'If you do nothing, sending pauses and everything else stays exactly where it is. We never delete a workspace for not paying, and you can pick up where you left off whenever you are ready.',
            ],
            eyebrow: 'Trial ending',
            preheader: 'Pick a plan to keep sending. Your data stays either way.',
            facts: $facts,
            actionLabel: 'Choose a plan',
            actionUrl: route('billing.index'),
            outro: [
                'Not the right fit? Reply and tell us why — it is the most useful email we get.',
            ],
            tone: 'warning',
        );
    }
}
