<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Domains;

/**
 * Looks up a domain's expiry date without any paid API or key.
 *
 * Strategy:
 *   1. RDAP over HTTPS (the modern, JSON replacement for WHOIS) — reliable for
 *      gTLDs (.com/.net/.org/…) and works from a cPanel backend on port 443.
 *   2. WHOIS on port 43 as a fallback for TLDs without RDAP (best effort; some
 *      shared hosts block outbound 43, in which case this degrades gracefully).
 *
 * The parsing methods are pure and unit-tested; only lookup() touches the
 * network. When nothing can be determined the result's expires_at is null and
 * the caller keeps any manually-entered date.
 */
final class DomainExpiryChecker
{
    /** Minimal WHOIS server map for common TLDs (fallback path). */
    private const WHOIS_SERVERS = [
        'com'   => 'whois.verisign-grs.com',
        'net'   => 'whois.verisign-grs.com',
        'org'   => 'whois.pir.org',
        'io'    => 'whois.nic.io',
        'co'    => 'whois.nic.co',
        'info'  => 'whois.nic.info',
        'biz'   => 'whois.nic.biz',
        'app'   => 'whois.nic.google',
        'dev'   => 'whois.nic.google',
        'za'    => 'whois.registry.net.za',
        'co.za' => 'whois.registry.net.za',
    ];

    public function __construct(
        private int $timeout = 12,
        private int $connectTimeout = 6,
    ) {
    }

    /**
     * @return array{domain:string, expires_at:?string, registrar:?string, source:?string, message:string}
     */
    public function lookup(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim(explode('/', $domain)[0], '.');

        $result = ['domain' => $domain, 'expires_at' => null, 'registrar' => null, 'source' => null, 'message' => ''];

        if ($domain === '' || !str_contains($domain, '.')) {
            $result['message'] = 'Invalid domain name.';
            return $result;
        }

        // 1) RDAP
        $rdap = $this->fetchRdap($domain);
        if ($rdap !== null) {
            $parsed = self::parseRdap($rdap);
            if ($parsed['expires_at'] !== null) {
                return array_merge($result, $parsed, ['source' => 'rdap', 'message' => 'Looked up via RDAP.']);
            }
        }

        // 2) WHOIS fallback
        $whois = $this->fetchWhois($domain);
        if ($whois !== null) {
            $expiry = self::parseWhoisExpiry($whois);
            if ($expiry !== null) {
                return array_merge($result, ['expires_at' => $expiry, 'source' => 'whois', 'message' => 'Looked up via WHOIS.']);
            }
        }

        $result['message'] = 'Could not determine expiry automatically for this TLD — enter it manually.';
        return $result;
    }

    // ------------------------------------------------------------------ RDAP

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRdap(string $domain): ?array
    {
        $body = $this->httpGet('https://rdap.org/domain/' . rawurlencode($domain));
        if ($body === null) {
            return null;
        }
        $json = json_decode($body, true);
        return is_array($json) ? $json : null;
    }

    /**
     * @param array<string, mixed> $json
     * @return array{expires_at:?string, registrar:?string}
     */
    public static function parseRdap(array $json): array
    {
        $expires = null;
        foreach ($json['events'] ?? [] as $event) {
            if (is_array($event) && ($event['eventAction'] ?? '') === 'expiration' && !empty($event['eventDate'])) {
                $ts = strtotime((string) $event['eventDate']);
                if ($ts !== false) {
                    $expires = date('Y-m-d', $ts);
                }
                break;
            }
        }

        $registrar = null;
        foreach ($json['entities'] ?? [] as $entity) {
            if (!is_array($entity) || !in_array('registrar', (array) ($entity['roles'] ?? []), true)) {
                continue;
            }
            // vcardArray = ["vcard", [ ["fn",{},"text","Registrar Name"], ... ]]
            foreach ($entity['vcardArray'][1] ?? [] as $field) {
                if (is_array($field) && ($field[0] ?? '') === 'fn') {
                    $registrar = (string) ($field[3] ?? '');
                    break 2;
                }
            }
        }

        return ['expires_at' => $expires, 'registrar' => $registrar ?: null];
    }

    // ----------------------------------------------------------------- WHOIS

    private function fetchWhois(string $domain): ?string
    {
        $server = $this->whoisServerFor($domain);
        if ($server === null) {
            return null;
        }

        $fp = @fsockopen($server, 43, $errno, $errstr, $this->connectTimeout);
        if ($fp === false) {
            return null; // port 43 likely blocked by the host — degrade gracefully
        }

        stream_set_timeout($fp, $this->timeout);
        fwrite($fp, $domain . "\r\n");
        $response = '';
        while (!feof($fp)) {
            $chunk = fgets($fp, 1024);
            if ($chunk === false) {
                break;
            }
            $response .= $chunk;
        }
        fclose($fp);

        return $response !== '' ? $response : null;
    }

    private function whoisServerFor(string $domain): ?string
    {
        $parts = explode('.', $domain);
        // Try a two-label suffix first (e.g. co.za) then the last label.
        if (count($parts) >= 2) {
            $two = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
            if (isset(self::WHOIS_SERVERS[$two])) {
                return self::WHOIS_SERVERS[$two];
            }
        }
        $tld = end($parts);
        return self::WHOIS_SERVERS[$tld] ?? null;
    }

    /**
     * Extract an expiry date from a raw WHOIS response.
     */
    public static function parseWhoisExpiry(string $text): ?string
    {
        $patterns = [
            '/(?:Registry Expiry Date|Registrar Registration Expiration Date|Expiry Date|Expiration Date|Expiration Time|paid-till|Renewal Date|expires on|Expires On|Expire Date)\s*:?\s*([0-9]{4}[-\/\.][0-9]{1,2}[-\/\.][0-9]{1,2}[T0-9:\.Z +-]*)/i',
            '/expires?\s*:?\s*([0-9]{1,2}[-\/ ][A-Za-z]{3,9}[-\/ ][0-9]{4})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $ts = strtotime(trim($m[1]));
                if ($ts !== false) {
                    return date('Y-m-d', $ts);
                }
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ HTTP

    private function httpGet(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'ParagonHostOps-DomainBot/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: application/rdap+json, application/json'],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return (is_string($body) && $status >= 200 && $status < 300) ? $body : null;
    }
}
