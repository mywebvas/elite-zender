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
}
