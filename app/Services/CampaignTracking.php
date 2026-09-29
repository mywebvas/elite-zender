<?php

namespace App\Services;

use App\Support\UnsubscribeLink;

/**
 * Open-pixel and click-relay injection.
 *
 * Extracted so the broadcast worker and the automation step runner share one
 * implementation. They did not: automation mail shipped with no pixel and no
 * rewritten links, so every journey-driven message was invisible in reporting
 * while the documentation claimed both paths were identical.
 */
final class CampaignTracking
{
    /** Rewrite links through the click relay and append the open pixel. */
    public function inject(string $html, string $campaignId, string $contactId): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $html = (string) preg_replace_callback(
            '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/i',
            function (array $matches) use ($campaignId, $contactId): string {
                $quote = $matches[1];
                $originalUrl = $matches[2];

                // mailto:, tel:, in-document anchors and the unsubscribe link
                // itself are left exactly as they are.
                if ($this->isNotTrackable($originalUrl)) {
                    return $matches[0];
                }

                $trackingUrl = route('tracking.click', [
                    'campaign' => $campaignId,
                    'contact' => $contactId,
                    'url' => base64_encode($originalUrl),
                ]);

                return str_replace(
                    "href={$quote}{$originalUrl}{$quote}",
                    "href={$quote}{$trackingUrl}{$quote}",
                    $matches[0],
                );
            },
            $html,
        );

        $pixelUrl = route('tracking.open', ['campaign' => $campaignId, 'contact' => $contactId]);

        return UnsubscribeLink::appendHtml(
            $html,
            '<img src="'.$pixelUrl.'" width="1" height="1" alt="" style="display:none;" />',
        );
    }

    private function isNotTrackable(string $url): bool
    {
        return str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:')
            || str_starts_with($url, '#')
            // Relaying the opt-out through the click tracker would break the
            // signature and turn every unsubscribe into a 403.
            || str_contains($url, '/unsubscribe/');
    }
}
