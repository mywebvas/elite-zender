<?php

namespace App\Notifications\Lifecycle;

use App\Lifecycle\Money;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * The receipt.
 *
 * Every business that pays you needs one for their books, and the absence of
 * one is a support ticket with a 100% hit rate. It also closes the loop on a
 * bank transfer, which is otherwise a payment into silence.
 */
class PaymentReceived extends LifecycleNotification
{
    public function __construct(
        private readonly Invoice $invoice,
        private readonly Payment $payment,
    ) {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $invoice = $this->invoice;

        return new LifecycleContent(
            subject: sprintf('Payment received — %s', Money::format($this->payment->amount, $this->payment->currency)),
            heading: 'Thank you — that is settled',
            greetingName: $this->firstName($notifiable),
            lines: [
                sprintf(
                    'We have received %s against invoice <strong>%s</strong>. Your %s plan is active and sending is fully available.',
                    Money::format($this->payment->amount, $this->payment->currency),
                    $invoice->number,
                    $invoice->planName('current'),
                ),
            ],
            eyebrow: 'Receipt',
            preheader: sprintf('Invoice %s is paid in full.', $invoice->number),
            facts: array_filter([
                'Invoice' => $invoice->number,
                'Paid' => Money::format($this->payment->amount, $this->payment->currency),
                'Method' => ucfirst($this->payment->gateway),
                'Reference' => $this->payment->reference,
                'Date' => ($this->payment->paid_at ?? now())->toFormattedDayDateString(),
            ]),
            actionLabel: 'View invoice',
            actionUrl: route('billing.invoices.show', $invoice->getKey()),
            outro: [
                'Need this as a PDF, or addressed to a different entity? Reply and we will sort it.',
            ],
            tone: 'success',
        );
    }
}
