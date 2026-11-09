<?php

namespace App\Support;

/**
 * Validates external hosts (SMTP relays, IMAP bounce mailboxes) to prevent
 * SSRF and connection injection attacks.
 */
final class SafeHost
{
    /** Prohibited hostnames that point to local/cloud metadata or internal loops. */
    private const BLOCKED_HOSTNAMES = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        'metadata.google.internal',
        'instance-data',
    ];

    /** Internal/local top-level and second-level domain suffixes. */
    private const BLOCKED_SUFFIXES = [
        '.local',
        '.internal',
        '.localdomain',
        '.home.arpa',
    ];

    /**
     * Checks if a host string is structurally valid and not pointing to
     * prohibited cloud metadata or internal destinations.
     */
    public static function isSafe(string $host): bool
    {
        $host = trim($host);

        if ($host === '' || strlen($host) > 255) {
            return false;
        }

        // Strip surrounding brackets for IPv6 if present
        $normalized = strtolower(trim($host, '[]'));

        // Disallow dangerous injection characters (spaces, command options, quotes, backslashes, colons, slashes)
        if (preg_match('/[\\s\\/\\\\;`|&\'"<>\\$]/', $host)) {
            return false;
        }

        // Disallow starting with a hyphen (flags injection for CLI/rsh/ssh tools)
        if (str_starts_with($host, '-')) {
            return false;
        }

        // Cloud metadata link-local address is ALWAYS blocked in all environments.
        if (self::isLinkLocal($normalized)) {
            return false;
        }

        // Explicit blocked hostnames
        if (in_array($normalized, self::BLOCKED_HOSTNAMES, true)) {
            // In testing or local environment, loopback is allowed for testing refusal/timeouts
            if (in_array($normalized, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true) && app()->environment('local', 'testing')) {
                return true;
            }

            return false;
        }

        // If IPv4 address
        if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // In production, block private and reserved IP ranges
            if (app()->isProduction() && ! filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }

            return true;
        }

        // If IPv6 address
        if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (app()->isProduction() && ! filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }

            return true;
        }

        // Check blocked domain suffixes in production
        if (app()->isProduction()) {
            foreach (self::BLOCKED_SUFFIXES as $suffix) {
                if (str_ends_with($normalized, $suffix)) {
                    return false;
                }
            }
        }

        // Validate hostname format (RFC 1123)
        if (! preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/', $host)) {
            return false;
        }

        return true;
    }

    private static function isLinkLocal(string $ip): bool
    {
        return str_starts_with($ip, '169.254.') || str_starts_with(strtolower($ip), 'fe80:') || $ip === 'fd00:ec2::254';
    }
}
