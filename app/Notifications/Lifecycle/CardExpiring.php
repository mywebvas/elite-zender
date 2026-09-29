<?php

namespace App\Notifications\Lifecycle;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * Involuntary churn, headed off.
 *
 * A meaningful share of every subscription business's churn is not a
 * decision at all — it is a card that aged out. The customer still wants the
 * product; nobody told them the number on file stopped working. It is the
 * cheapest churn there is to prevent and the most infuriating to lose.
 *
 * Sent before the expiry rather than after the decline, so the fix is thirty
 * seconds of admin instead of a dunning cycle, a suspension and an apology.
 */
class CardExpiring extends LifecycleNotification
{
    public function __construct(
        private readonly Subscription $subscription,
        private readonly bool $alreadyExpired = false,
    ) {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $card = trim(($this->subscription->card_brand ?: 'Card').' ending '.($this->subscription->card_last_four ?: '••••'));
        $expires = sprintf('%02d/%d', (int) $this->subscription->card_exp_month, (int) $this->subscription->card_exp_year);

        return new LifecycleContent(
            subject: $this->alreadyExpired
                ? 'The card on your account has expired'
                : 'Your card expires soon',
            heading: $this->alreadyExpired ? 'Your saved card has expired' : 'Your card is about to expire',
            greetingName: $this->firstName($notifiable),
            lines: $this->alreadyExpired
                ? [
                    sprintf('The %s we have on file expired in <strong>%s</strong>, so your next renewal will be declined.', e($card), $expires),
                    'Updating it takes about thirty seconds and prevents the whole dunning sequence — declined charge, retries, and eventually paused sending — none of which you want and none of which we enjoy sending.',
                ]
                : [
                    sprintf('The %s we have on file expires in <strong>%s</strong>.', e($card), $expires),
                    sprintf(
                        'Your next renewal is due on <strong>%s</strong>. If the card expires first the charge will be declined, so it is worth updating now while it is a thirty-second job rather than a support ticket.',
                        $this->subscription->current_period_end?->toFormattedDayDateString() ?? 'your renewal date',
                    ),
                ],
            eyebrow: 'Payment method',
            preheader: sprintf('%s expires %s. Updating it takes thirty seconds.', $card, $expires),
            facts: array_filter([
                'Card' => $card,
                'Expires' => $expires,
                'Next renewal' => $this->subscription->current_period_end?->toFormattedDayDateString(),
            ]),
            actionLabel: 'Update payment method',
            actionUrl: route('billing.index'),
            outro: ['Prefer to pay by bank transfer instead? Remove the card and we will email you an invoice before each renewal.'],
            tone: $this->alreadyExpired ? 'danger' : 'warning',
        );
    }
}
