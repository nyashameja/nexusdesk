<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm;

use ParagonHostOps\Services\Whm\Support\Units;

/**
 * Pure transformation layer: converts raw WHM API payloads into the stable
 * record shapes the repositories persist. Kept side-effect free so it can be
 * unit-tested exhaustively against fixtures.
 */
final class WhmNormalizer
{
    /**
     * Normalise one entry from listaccts / accountsummary.
     *
     * @param array<string, mixed> $acct
     * @return array<string, mixed>
     */
    public function account(array $acct): array
    {
        $diskUsed  = Units::toMegabytes($acct['diskused']  ?? null);
        $diskLimit = Units::toMegabytes($acct['disklimit'] ?? null);

        return [
            'username'       => (string) ($acct['user'] ?? ''),
            'domain'         => (string) ($acct['domain'] ?? ''),
            'owner'          => isset($acct['owner']) ? (string) $acct['owner'] : null,
            'email'          => isset($acct['email']) ? (string) $acct['email'] : null,
            'package'        => isset($acct['plan']) ? (string) $acct['plan'] : null,
            'ip_address'     => isset($acct['ip']) ? (string) $acct['ip'] : null,
            'theme'          => isset($acct['theme']) ? (string) $acct['theme'] : null,
            'locale'         => isset($acct['locale']) ? (string) $acct['locale'] : null,
            'suspended'      => (int) (($acct['suspended'] ?? 0) ? 1 : 0),
            'suspend_reason' => $this->suspendReason($acct),
            'whm_created_at' => Units::parseStartDate($acct['startdate'] ?? null),
            'disk_used_mb'   => $diskUsed,
            'disk_limit_mb'  => $diskLimit,
        ];
    }

    /**
     * @param array<string, mixed> $acct
     */
    private function suspendReason(array $acct): ?string
    {
        if ((int) (($acct['suspended'] ?? 0) ? 1 : 0) !== 1) {
            return null;
        }
        $reason = trim((string) ($acct['suspendreason'] ?? ''));
        if ($reason === '' || strcasecmp($reason, 'not suspended') === 0) {
            return null;
        }
        return $reason;
    }

    /**
     * Normalise a listpkgs entry.
     *
     * @param array<string, mixed> $pkg
     * @return array<string, mixed>
     */
    public function package(array $pkg): array
    {
        return [
            'name'          => (string) ($pkg['name'] ?? ''),
            'disk_quota_mb' => Units::toMegabytes($pkg['QUOTA'] ?? null),
            'bandwidth_mb'  => Units::toMegabytes($pkg['BWLIMIT'] ?? null),
            'max_addon'     => $this->intOrNull($pkg['MAXADDON'] ?? null),
            'max_sub'       => $this->intOrNull($pkg['MAXSUB'] ?? null),
            'max_email'     => $this->intOrNull($pkg['MAXPOP'] ?? null),
        ];
    }

    /**
     * Normalise a showbw entry into per-username bandwidth in MB.
     *
     * @param array<string, mixed> $entry
     * @return array{username:string, bandwidth_used_mb:int, bandwidth_limit_mb:int}
     */
    public function bandwidth(array $entry): array
    {
        return [
            'username'           => (string) ($entry['user'] ?? ''),
            'bandwidth_used_mb'  => Units::bytesToMegabytes($entry['totalbytes'] ?? 0),
            'bandwidth_limit_mb' => Units::bytesToMegabytes($entry['limit'] ?? 0),
        ];
    }

    /**
     * Normalise a fetch_ssl_vhosts entry into an SSL certificate record.
     *
     * @param array<string, mixed> $vhost
     * @return array<string, mixed>
     */
    public function ssl(array $vhost): array
    {
        $crt      = is_array($vhost['crt'] ?? null) ? $vhost['crt'] : [];
        $notAfter = $crt['not_after'] ?? null;
        $days     = Units::daysUntil($notAfter);
        $domains  = $crt['domains'] ?? [];

        return [
            'domain'         => (string) ($vhost['servername'] ?? ''),
            'issuer'         => isset($crt['issuer.commonName']) ? (string) $crt['issuer.commonName'] : ($crt['issuer']['commonName'] ?? null),
            'cert_type'      => isset($crt['validation_type']) ? (string) $crt['validation_type'] : null,
            'valid_from'     => Units::timestampToDate($crt['not_before'] ?? null),
            'valid_to'       => Units::timestampToDate($notAfter),
            'days_remaining' => $days,
            'covered_hosts'  => is_array($domains) ? implode(', ', array_map('strval', $domains)) : (string) $domains,
            'status'         => $this->sslStatus($days),
        ];
    }

    /**
     * Derive an SSL status band from days remaining.
     */
    public function sslStatus(?int $days): string
    {
        if ($days === null) {
            return 'unknown';
        }
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 30) {
            return 'expiring';
        }
        return 'valid';
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '' || strcasecmp($raw, 'unlimited') === 0) {
            return null;
        }
        return is_numeric($raw) ? (int) $raw : null;
    }
}
