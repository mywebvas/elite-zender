<?php

namespace App\Notifications\Lifecycle;

use App\Lifecycle\Money;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * Dunning, in words rather than in a log file.
 *
 * The previous behaviour recorded the decline, scheduled a retry, and told
 * nobody: the first the customer knew was that sending had stopped. Most
 * failed renewals are an expired card, which takes the customer thirty
 * seconds to fix — but only if somebody tells them.
 *
 * The tone escalates with the attempt, and the last attempt says plainly
 * what happens next. Vague threats do not get cards updated; dates do.
 */
class PaymentFailed extends LifecycleNotification
{
    public function __construct(
        private readonly Subscription $subscription,
        private readonly Invoice $invoice,
        private readonly int $attempt,
        private readonly ?string $reason = null,
    ) {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $final = $this->subscription->next_retry_at === null;
        $amount = Money::format($this->invoice->balance(), $this->invoice->currency);

        $lines = [
            sprintf(
                'We tried to charge %s for invoice <strong>%s</strong> and the payment did not go through%s.',
                $amount,
                $this->invoice->number,
                $this->reason !== null ? ' — '.e($this->reason) : '',
            ),
        ];

        $lines[] = $final
            ? 'This was our last automatic attempt. Sending stays available through the grace window, and we will let you know before anything is paused.'
            : sprintf(
                'We will try again on <strong>%s</strong>. Nothing is paused in the meantime — your campaigns, lists and relays are untouched.',
                $this->subscription->next_retry_at?->toFormattedDayDateString() ?? 'the next retry date',
            );

        $lines[] = 'Nine times out of ten this is simply a card that has expired or a bank declining an unfamiliar merchant. Updating the card takes about thirty seconds.';

        return new LifecycleContent(
            subject: $final
                ? sprintf('Action needed: %s could not be collected', $amount)
                : sprintf('Your payment of %s did not go through', $amount),
            heading: $final ? 'We could not collect your payment' : 'That payment did not go through',
            greetingName: $this->firstName($notifiable),
            lines: $lines,
            eyebrow: sprintf('Attempt %d', $this->attempt),
            preheader: 'Usually an expired card. Updating it takes about thirty seconds.',
            facts: array_filter([
                'Invoice' => $this->invoice->number,
                'Amount' => $amount,
                'Next attempt' => $final ? 'None — manual payment needed' : $this->subscription->next_retry_at?->toFormattedDayDateString(),
            ]),
            actionLabel: 'Update payment method',
            actionUrl: route('billing.index'),
            outro: [
                'Paying by bank transfer instead is completely fine — the invoice page has the details.',
            ],
            tone: $final ? 'danger' : 'warning',
        );
    }
}
