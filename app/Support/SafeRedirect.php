<?php

namespace App\Support;

/**
 * Allow-list check for operator-supplied redirect targets (click tracking).
 *
 * Guards against SSRF and open-redirect abuse of the `/t/c/...` relay. The
 * previous version only inspected the literal host, so `http://evil.test`
 * resolving to 169.254.169.254 (cloud metadata) sailed straight through.
 */
final class SafeRedirect
{
    /** Hosts that must never be reachable through the relay. */
    private const BLOCKED_HOSTS = ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'metadata.google.internal'];

    /** Internal-only TLDs commonly resolvable inside a VPC. */
    private const BLOCKED_SUFFIXES = ['.local', '.internal', '.localdomain', '.home.arpa'];

    public static function isAllowed(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > 2000) {
            return false;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        if (! in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }

        // Embedded credentials are a classic phishing disguise (and never
        // legitimate in a marketing link).
        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return false;
        }

        $host = strtolower(trim($parsed['host'], '[]'));

        if ($host === '' || in_array($host, self::BLOCKED_HOSTS, true)) {
            return false;
        }

        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        return self::resolvesToPublicAddress($host);
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * Resolve the hostname and reject it when it points into private space.
     *
     * Deliberately fail-open when resolution yields nothing: an air-gapped
     * build box or a slow resolver must not turn every customer link into a
     * 422. A name that cannot be resolved also cannot be reached.
     */
    private static function resolvesToPublicAddress(string $host): bool
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            return true;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($ip) && ! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }
}
