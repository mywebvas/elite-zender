<?php

namespace App\Notifications\Lifecycle;

use App\Lifecycle\Money;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * Abandoned-checkout recovery, and then a due-soon nudge.
 *
 * An unpaid invoice a day after it was raised almost always means the same
 * thing: the customer opened the payment page, was interrupted, and never
 * came back. Recovering that is the single highest-return email in the whole
 * lifecycle, and the whole trick is a direct link back to the exact invoice
 * rather than a generic "manage billing".
 *
 * Exactly two nudges, then the dunning schedule takes over. A third reminder
 * is not persistence, it is harassment, and it trains people to filter you.
 */
class InvoiceReminder extends LifecycleNotification
{
    public function __construct(
        private readonly Invoice $invoice,
        private readonly int $nudge,
    ) {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $invoice = $this->invoice;
        $amount = Money::format($invoice->balance(), $invoice->currency);
        $first = $this->nudge <= 1;

        return new LifecycleContent(
            subject: $first
                ? sprintf('You left %s unpaid — finish in one click', $amount)
                : sprintf('Reminder: invoice %s is due %s', $invoice->number, $invoice->due_at?->diffForHumans() ?? 'soon'),
            heading: $first ? 'Pick up where you left off' : 'A quick reminder',
            greetingName: $this->firstName($notifiable),
            lines: $first
                ? [
                    sprintf('Invoice <strong>%s</strong> for %s is still open. If the payment page timed out or something came up, the link below takes you straight back to it — nothing has been lost.', $invoice->number, $amount),
                    'If you have already paid by bank transfer, ignore this: we match transfers by hand and it can take a few hours.',
                ]
                : [
                    sprintf(
                        'Invoice <strong>%s</strong> for %s is due on <strong>%s</strong>. Settling it keeps your sending uninterrupted.',
                        $invoice->number,
                        $amount,
                        $invoice->due_at?->toFormattedDayDateString() ?? 'the due date',
                    ),
                ],
            eyebrow: $first ? 'Unfinished payment' : 'Payment due',
            preheader: $first
                ? 'Your payment did not complete — the link below goes straight back to it.'
                : sprintf('%s due %s.', $amount, $invoice->due_at?->diffForHumans() ?? 'soon'),
            facts: array_filter([
                'Invoice' => $invoice->number,
                'Outstanding' => $amount,
                'Due' => $invoice->due_at?->toFormattedDayDateString(),
            ]),
            actionLabel: $first ? 'Finish payment' : 'Pay invoice',
            actionUrl: route('billing.invoices.show', $invoice->getKey()),
            outro: [
                'Need a different payment method, a longer window, or a copy for your finance team? Just reply.',
            ],
            tone: $first ? 'brand' : 'warning',
        );
    }
}
