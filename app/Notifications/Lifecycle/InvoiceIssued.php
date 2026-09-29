<?php

namespace App\Notifications\Lifecycle;

use App\Lifecycle\Money;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * An invoice exists and is waiting to be paid.
 *
 * Sent the moment one is raised — including for offline payers, who are the
 * reason invoices exist separately from charges. Previously nothing was sent
 * at all: a customer who closed the checkout tab had no record that they
 * owed anything until sending stopped a week later.
 */
class InvoiceIssued extends LifecycleNotification
{
    public function __construct(private readonly Invoice $invoice)
    {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $invoice = $this->invoice;

        return new LifecycleContent(
            subject: sprintf('Invoice %s — %s due', $invoice->number, Money::format($invoice->total, $invoice->currency)),
            heading: 'Your invoice is ready',
            greetingName: $this->firstName($notifiable),
            lines: [
                sprintf(
                    'Invoice <strong>%s</strong> for %s is open. Pay by card, bank transfer or USSD — whichever suits — and your plan continues without interruption.',
                    $invoice->number,
                    $invoice->planName(),
                ),
            ],
            eyebrow: 'Invoice',
            preheader: sprintf('%s due by %s.', Money::format($invoice->total, $invoice->currency), $invoice->due_at?->toFormattedDayDateString() ?? 'the due date'),
            facts: array_filter([
                'Invoice' => $invoice->number,
                'Amount' => Money::format($invoice->total, $invoice->currency),
                'Due' => $invoice->due_at?->toFormattedDayDateString(),
                'Period' => $invoice->period_start && $invoice->period_end
                    ? $invoice->period_start->toFormattedDayDateString().' – '.$invoice->period_end->toFormattedDayDateString()
                    : null,
            ]),
            actionLabel: 'View and pay',
            actionUrl: route('billing.invoices.show', $invoice->getKey()),
            outro: [
                'Paying by bank transfer? Upload the receipt on the invoice page and we will confirm it by hand, usually the same day.',
            ],
        );
    }
}
