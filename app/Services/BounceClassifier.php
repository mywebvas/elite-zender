<?php

namespace App\Services;

/**
 * Classifies a Delivery Status Notification into hard / soft / complaint.
 *
 * Driven by the RFC 3463 enhanced status code where present (`5.1.1` etc.),
 * falling back to the SMTP reply class and finally to well-known phrases. The
 * distinction matters commercially: a hard bounce must suppress the address
 * permanently, a soft bounce must not.
 */
final class BounceClassifier
{
    public const HARD = 'hard';

    public const SOFT = 'soft';

    public const COMPLAINT = 'complaint';

    /** Enhanced status subjects/details that are permanent by definition. */
    private const HARD_ENHANCED = ['5.1.1', '5.1.2', '5.1.3', '5.1.6', '5.1.10', '5.2.1', '5.4.1', '5.7.1'];

    private const HARD_PHRASES = [
        'user unknown',
        'no such user',
        'unknown recipient',
        'recipient address rejected',
        'address does not exist',
        'mailbox unavailable',
        'invalid recipient',
        'does not exist',
        'account has been disabled',
    ];

    private const SOFT_PHRASES = [
        'mailbox full',
        'over quota',
        'quota exceeded',
        'temporarily deferred',
        'try again later',
        'greylisted',
        'timed out',
        'connection refused',
    ];

    private const COMPLAINT_PHRASES = [
        'feedback-type: abuse',
        'this is an email abuse report',
        'complaint-about',
    ];

    public function classify(string $rawMessage): string
    {
        $body = mb_strtolower($rawMessage);

        foreach (self::COMPLAINT_PHRASES as $needle) {
            if (str_contains($body, $needle)) {
                return self::COMPLAINT;
            }
        }

        if (preg_match('/status:\s*([245])\.(\d+)\.(\d+)/', $body, $matches) === 1) {
            $code = $matches[1].'.'.$matches[2].'.'.$matches[3];

            if (in_array($code, self::HARD_ENHANCED, true)) {
                return self::HARD;
            }

            return $matches[1] === '5' ? self::HARD : self::SOFT;
        }

        foreach (self::HARD_PHRASES as $needle) {
            if (str_contains($body, $needle)) {
                return self::HARD;
            }
        }

        foreach (self::SOFT_PHRASES as $needle) {
            if (str_contains($body, $needle)) {
                return self::SOFT;
            }
        }

        // Unknown reasons are treated as soft: wrongly suppressing a valid
        // subscriber is far more expensive than one extra retry.
        return self::SOFT;
    }

    /** Extract the failing recipient from a DSN body, if present. */
    public function extractRecipient(string $rawMessage): ?string
    {
        if (preg_match('/final-recipient:\s*rfc822;\s*([^\s<>]+@[^\s<>]+)/i', $rawMessage, $m) === 1) {
            return mb_strtolower(trim($m[1], " \t\n\r\0\x0B.<>"));
        }

        if (preg_match('/original-recipient:\s*rfc822;\s*([^\s<>]+@[^\s<>]+)/i', $rawMessage, $m) === 1) {
            return mb_strtolower(trim($m[1], " \t\n\r\0\x0B.<>"));
        }

        return null;
    }
}
