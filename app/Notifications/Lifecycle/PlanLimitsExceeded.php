<?php

namespace App\Notifications\Lifecycle;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * "Your workspace is above what this plan allows."
 *
 * Sent after a downgrade leaves a workspace holding more than the new tier
 * permits. The alternative designs are both worse: deleting the excess is
 * unforgivable, and saying nothing means the customer discovers it when an
 * invitation or an import silently refuses months later.
 *
 * The tone is deliberately not a threat. Nothing has been taken away, and
 * nothing will be — the message exists so the customer can make an informed
 * choice between trimming and upgrading.
 */
class PlanLimitsExceeded extends LifecycleNotification
{
    /**
     * @param  array<string, array{used: int, limit: int, over: int, label: string}>  $overages
     */
    public function __construct(
        private readonly Subscription $subscription,
        private readonly array $overages,
    ) {
        parent::__construct();
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $facts = [];

        foreach ($this->overages as $overage) {
            $facts[ucfirst($overage['label'])] = sprintf(
                '%s of %s (%s over)',
                number_format($overage['used']),
                number_format($overage['limit']),
                number_format($overage['over']),
            );
        }

        return new LifecycleContent(
            subject: sprintf('Your workspace is above the %s plan limits', $this->subscription->planName()),
            heading: 'You are above your plan limits',
            greetingName: $this->firstName($notifiable),
            lines: [
                sprintf(
                    'Your workspace is now on <strong>%s</strong> and is holding more than that plan includes.',
                    e($this->subscription->planName()),
                ),
                '<strong>Nothing has been deleted and nothing will be.</strong> Your contacts, campaigns and history are all intact, and everything you have already built keeps working.',
                'What you cannot do while you are over is add more of the same thing — a new team member, a new relay, another import. Either trim back to fit, or move up a tier and carry on.',
            ],
            eyebrow: 'Plan limits',
            preheader: 'Nothing deleted — you just cannot add more until you are back inside the plan.',
            facts: $facts,
            actionLabel: 'Review plans',
            actionUrl: route('billing.index'),
            outro: [
                'If the numbers look wrong, reply and we will check them with you before you pay for anything.',
            ],
            tone: 'warning',
        );
    }
}
