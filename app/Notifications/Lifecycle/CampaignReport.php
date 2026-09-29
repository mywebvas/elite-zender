<?php

namespace App\Notifications\Lifecycle;

use App\Models\Campaign;
use App\Models\User;
use App\Notifications\LifecycleContent;
use App\Notifications\LifecycleNotification;

/**
 * "Here is what your campaign did."
 *
 * The retention email for a product whose whole value is measurement. It
 * arrives when the customer cares most — just after a send — reinforces that
 * the thing they paid for worked, and pulls them back into the app on the
 * strength of their own numbers rather than a marketing prompt.
 *
 * Event-driven rather than a weekly digest on purpose: a digest arrives
 * whether or not anything happened, and a report that sometimes says
 * "nothing happened" trains people to stop opening it.
 */
class CampaignReport extends LifecycleNotification
{
    public function __construct(
        private readonly Campaign $campaign,
        private readonly int $opens,
        private readonly int $clicks,
    ) {
        parent::__construct();
    }

    public function category(): string
    {
        return 'reports';
    }

    protected function content(User $notifiable): LifecycleContent
    {
        $sent = (int) $this->campaign->sent_count;
        $rate = static fn (int $n): string => $sent > 0 ? round($n / $sent * 100, 1).'%' : '—';

        $lines = [
            sprintf(
                '<strong>%s</strong> finished delivering. Here is how it did.',
                e($this->campaign->name),
            ),
        ];

        // One honest observation rather than a wall of benchmarks.
        if ($sent > 0 && $this->opens / max(1, $sent) < 0.1) {
            $lines[] = 'Opens are on the low side. The usual causes are a subject line that reads as promotional, a from-address the recipient does not recognise, or a list that has gone cold — in that order.';
        } elseif ($sent > 0 && $this->clicks > 0 && $this->opens > 0 && $this->clicks / $this->opens > 0.15) {
            $lines[] = 'That is a strong click-to-open rate — the people who opened it found what they wanted. Worth reusing the structure.';
        }

        return new LifecycleContent(
            subject: sprintf('%s: %s opens, %s clicks', $this->campaign->name, number_format($this->opens), number_format($this->clicks)),
            heading: 'Your campaign has finished',
            greetingName: $this->firstName($notifiable),
            lines: $lines,
            eyebrow: 'Campaign report',
            preheader: sprintf('%s delivered · %s opened · %s clicked', number_format($sent), $rate($this->opens), $rate($this->clicks)),
            facts: array_filter([
                'Delivered' => number_format($sent),
                'Opens' => sprintf('%s (%s)', number_format($this->opens), $rate($this->opens)),
                'Clicks' => sprintf('%s (%s)', number_format($this->clicks), $rate($this->clicks)),
                'Failed' => $this->campaign->failed_count > 0 ? number_format($this->campaign->failed_count) : null,
                'Skipped' => $this->campaign->skipped_count > 0 ? number_format($this->campaign->skipped_count).' (suppressed or opted out)' : null,
            ]),
            actionLabel: 'See the full breakdown',
            actionUrl: route('campaigns.show', $this->campaign->getKey()),
            tone: 'success',
        );
    }
}
