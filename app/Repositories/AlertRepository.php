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

    /** Client display name, shared by every candidate query. */
    private const CLIENT_NAME =
        "COALESCE(NULLIF(c.company_name,''), NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)),''))";

    /**
     * SSL certificates within 30 days of expiry (or already expired), with the
     * owning account and client so notifications can name them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sslCandidates(): array
    {
        return $this->db->all(
            "SELECT s.domain, s.days_remaining, s.valid_to, s.issuer,
                    a.id AS account_id, a.username, a.package,
                    " . self::CLIENT_NAME . " AS client_name
               FROM whm_ssl_certificates s
          LEFT JOIN whm_accounts a ON a.id = s.account_id AND a.deleted_at IS NULL
          LEFT JOIN clients c ON c.id = a.client_id
              WHERE s.days_remaining IS NOT NULL AND s.days_remaining <= 30
           ORDER BY s.days_remaining ASC"
        );
    }

    /**
     * Domains within 30 days of expiry (or already expired), with registrar,
     * auto-renew state and client.
     *
     * @return array<int, array<string, mixed>>
     */
    public function domainCandidates(): array
    {
        return $this->db->all(
            "SELECT d.id, d.domain, d.expires_at, d.registrar, d.auto_renew,
                    DATEDIFF(d.expires_at, UTC_DATE()) AS days,
                    " . self::CLIENT_NAME . " AS client_name
               FROM domains d
          LEFT JOIN clients c ON c.id = d.client_id
              WHERE d.deleted_at IS NULL AND d.expires_at IS NOT NULL
                AND DATEDIFF(d.expires_at, UTC_DATE()) <= 30
           ORDER BY days ASC"
        );
    }

    /**
     * Accounts at or above 80% disk or bandwidth usage, including the raw
     * MB figures so alerts can show "8.7 GB of 10 GB", not just a percentage.
     *
     * @return array<int, array<string, mixed>>
     */
    public function usageCandidates(): array
    {
        return $this->db->all(
            "SELECT a.id, a.domain, a.username, a.package,
                    " . self::CLIENT_NAME . " AS client_name,
                    u.disk_used_mb, u.disk_limit_mb,
                    u.bandwidth_used_mb, u.bandwidth_limit_mb,
                    CASE WHEN u.disk_limit_mb > 0 THEN ROUND(u.disk_used_mb / u.disk_limit_mb * 100, 1) ELSE 0 END AS disk_pct,
                    CASE WHEN u.bandwidth_limit_mb > 0 THEN ROUND(u.bandwidth_used_mb / u.bandwidth_limit_mb * 100, 1) ELSE 0 END AS bw_pct
               FROM whm_accounts a
               JOIN whm_account_usage u ON u.account_id = a.id
          LEFT JOIN clients c ON c.id = a.client_id
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
