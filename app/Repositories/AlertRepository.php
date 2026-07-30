<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Scans for alert conditions and tracks which alerts have already been sent
 * (so a threshold fires once per crossing, not every cron run).
 */
final class AlertRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * SSL certificates within 30 days of expiry (or already expired).
     *
     * @return array<int, array<string, mixed>>
     */
    public function sslCandidates(): array
    {
        return $this->db->all(
            "SELECT domain, days_remaining
               FROM whm_ssl_certificates
              WHERE days_remaining IS NOT NULL AND days_remaining <= 30
           ORDER BY days_remaining ASC"
        );
    }

    /**
     * Domains within 30 days of expiry (or already expired).
     *
     * @return array<int, array<string, mixed>>
     */
    public function domainCandidates(): array
    {
        return $this->db->all(
            "SELECT id, domain, DATEDIFF(expires_at, UTC_DATE()) AS days
               FROM domains
              WHERE deleted_at IS NULL AND expires_at IS NOT NULL
                AND DATEDIFF(expires_at, UTC_DATE()) <= 30
           ORDER BY days ASC"
        );
    }

    /**
     * Accounts at or above 80% disk or bandwidth usage.
     *
     * @return array<int, array<string, mixed>>
     */
    public function usageCandidates(): array
    {
        return $this->db->all(
            "SELECT a.id, a.domain,
                    CASE WHEN u.disk_limit_mb > 0 THEN ROUND(u.disk_used_mb / u.disk_limit_mb * 100, 1) ELSE 0 END AS disk_pct,
                    CASE WHEN u.bandwidth_limit_mb > 0 THEN ROUND(u.bandwidth_used_mb / u.bandwidth_limit_mb * 100, 1) ELSE 0 END AS bw_pct
               FROM whm_accounts a
               JOIN whm_account_usage u ON u.account_id = a.id
              WHERE a.deleted_at IS NULL
                AND ((u.disk_limit_mb > 0 AND u.disk_used_mb / u.disk_limit_mb >= 0.8)
                  OR (u.bandwidth_limit_mb > 0 AND u.bandwidth_used_mb / u.bandwidth_limit_mb >= 0.8))"
        );
    }

    /**
     * @return array<int, string> Alert keys already sent.
     */
    public function sentKeys(): array
    {
        $rows = $this->db->all('SELECT alert_key FROM alerts_log');
        return array_map(static fn (array $r): string => (string) $r['alert_key'], $rows);
    }

    /**
     * Record newly-sent alert keys.
     *
     * @param array<int, array{key:string, category:string, message:string}> $alerts
     */
    public function markSent(array $alerts): void
    {
        foreach ($alerts as $a) {
            $this->db->execute(
                'INSERT INTO alerts_log (alert_key, category, message, sent_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE sent_at = UTC_TIMESTAMP()',
                [$a['key'], $a['category'], mb_substr($a['message'], 0, 500)]
            );
        }
    }

    /**
     * Delete log rows whose condition is no longer active, so the alert can
     * re-fire next time it crosses the threshold.
     *
     * @param array<int, string> $activeKeys
     */
    public function deleteResolved(array $activeKeys): int
    {
        if ($activeKeys === []) {
            return $this->db->execute('DELETE FROM alerts_log');
        }
        $placeholders = implode(',', array_fill(0, count($activeKeys), '?'));
        return $this->db->execute(
            "DELETE FROM alerts_log WHERE alert_key NOT IN ($placeholders)",
            $activeKeys
        );
    }
}
