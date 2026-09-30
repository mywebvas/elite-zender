<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Support\Facades\URL;

/**
 * Tamper-proof unsubscribe URLs.
 *
 * The previous links were plain `/unsubscribe/{campaignUuid}/{contactUuid}`
 * paths: anybody holding (or guessing) two ids could unsubscribe an arbitrary
 * recipient, and nothing tied the pair together. Signing the route binds the
 * campaign, the contact and the app key into one HMAC.
 *
 * The signature deliberately never expires — a message sits in an inbox for
 * years and the unsubscribe link must keep working (CAN-SPAM requires the
 * mechanism to stay live for at least 30 days; in practice: forever).
 */
final class UnsubscribeLink
{
    public static function for(Campaign $campaign, Contact $contact): string
    {
        return URL::signedRoute('unsubscribe', [
            'campaign' => $campaign->getKey(),
            'contact' => $contact->getKey(),
        ]);
    }

    /**
     * The visible opt-out block appended to every outgoing message.
     *
     * CAN-SPAM §7704(a)(5) and GDPR both want a clear, conspicuous opt-out in
     * the body of the message. The `List-Unsubscribe` header alone does not
     * satisfy either, and plenty of clients never surface it. Broadcasts and
     * automations share this markup so the two can never drift — automation
     * mail used to ship with no visible link at all.
     */
    public static function footerHtml(string $unsubUrl): string
    {
        return '<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: center;">'
            .'<p>You are receiving this because you opted in. '
            .'<a href="'.e($unsubUrl).'" style="color: #64748b; text-decoration: underline;">Unsubscribe here</a>.</p></div>';
    }

    /** Insert the footer just before `</body>`, or append when there is none. */
    public static function appendFooter(string $html, string $unsubUrl): string
    {
        return self::appendHtml($html, self::footerHtml($unsubUrl));
    }

    public static function appendTextFooter(string $text, string $unsubUrl): string
    {
        return $text === '' ? $text : $text."\n\n---\nTo unsubscribe, visit: {$unsubUrl}";
    }

    /** Splice a fragment into an HTML document at the safest available point. */
    public static function appendHtml(string $html, string $fragment): string
    {
        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $fragment."\n</body>", $html);
        }

        return $html."\n".$fragment;
    }
}
