<?php

namespace App\Notifications\Lifecycle;

use App\Lifecycle\Money;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * "We are about to charge your card."
 *
 * Not a courtesy. An unannounced recurring debit is the most common trigger
 * for a chargeback, and a chargeback costs the fee, the revenue and a mark
 * against the merchant account. Telling people first is cheaper than every
 * alternative — and it is what card-network rules expect of a subscription
 * merchant.
 *
 * Only ever sent when a charge will genuinely be attempted (a card is on
 * file and the plan costs money); otherwise it is a lie that arrives monthly.
 */
class RenewalReminder extends LifecycleNotification
{
    public function __construct(private readonly Subscription $subscription)
    {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $subscription = $this->subscription;
        $renewsAt = $subscription->current_period_end;

        return new LifecycleContent(
            subject: sprintf('Your %s plan renews %s', $subscription->planName('subscription'), $renewsAt?->diffForHumans() ?? 'soon'),
            heading: 'Heads up: renewal coming',
            greetingName: $this->firstName($notifiable),
            lines: [
                sprintf(
                    'On <strong>%s</strong> we will charge %s to your %s card ending %s, and your plan continues for another month.',
                    $renewsAt?->toFormattedDayDateString() ?? 'your renewal date',
                    Money::format($subscription->amount, $subscription->currency),
                    $subscription->card_brand ?: 'saved',
                    $subscription->card_last_four ?: '••••',
                ),
                'Nothing to do if that is what you want. If you would rather change plan, switch card, or stop the renewal, everything is one click away and there is no penalty for any of it.',
            ],
            eyebrow: 'Upcoming charge',
            preheader: sprintf('%s on %s. Change or cancel any time before then.', Money::format($subscription->amount, $subscription->currency), $renewsAt?->toFormattedDayDateString() ?? 'renewal'),
            facts: array_filter([
                'Plan' => $subscription->planName(),
                'Amount' => Money::format($subscription->amount, $subscription->currency),
                'Renews' => $renewsAt?->toFormattedDayDateString(),
                'Card' => $subscription->card_last_four ? trim(($subscription->card_brand ?: 'Card').' ••••'.$subscription->card_last_four) : null,
            ]),
            actionLabel: 'Manage billing',
            actionUrl: route('billing.index'),
        );
    }
}
