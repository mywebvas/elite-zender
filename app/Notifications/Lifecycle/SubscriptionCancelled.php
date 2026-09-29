<?php

namespace App\Notifications\Lifecycle;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * Cancellation, confirmed — and quietly reversible.
 *
 * Two jobs. First, remove all doubt about what happens and when, because an
 * unconfirmed cancellation generates a chargeback from someone who is not
 * sure it worked. Second, make undoing it a single click: a meaningful share
 * of cancellations are a mis-click, a test, or a decision the team reverses
 * within a week, and an undo link converts far better than a re-signup flow.
 */
class SubscriptionCancelled extends LifecycleNotification
{
    public function __construct(private readonly Subscription $subscription)
    {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $endsAt = $this->subscription->cancel_at ?? $this->subscription->current_period_end;

        return new LifecycleContent(
            subject: 'Your plan will not renew',
            heading: 'Cancellation confirmed',
            greetingName: $this->firstName($notifiable),
            lines: [
                sprintf(
                    'Your %s plan will not renew. You keep everything you have paid for until <strong>%s</strong> — full sending, every feature, no degradation in the meantime.',
                    $this->subscription->planName(),
                    $endsAt?->toFormattedDayDateString() ?? 'the end of the period',
                ),
                'After that the workspace moves to the free plan. It is not deleted: your contacts, campaigns and history stay, and you can upgrade again at any point and carry straight on.',
                'Changed your mind? One click puts it back exactly as it was — no new invoice, no re-entering a card.',
            ],
            eyebrow: 'Cancelled',
            preheader: sprintf('Active until %s. One click undoes this.', $endsAt?->toFormattedDayDateString() ?? 'period end'),
            facts: array_filter([
                'Plan' => $this->subscription->planName(),
                'Access until' => $endsAt?->toFormattedDayDateString(),
                'Then' => 'Free plan — data retained',
            ]),
            actionLabel: 'Undo cancellation',
            actionUrl: route('billing.index'),
            outro: [
                'If you have a minute, reply with what pushed you to cancel. We read every one, and it is how the roadmap gets decided.',
            ],
            tone: 'warning',
        );
    }
}
