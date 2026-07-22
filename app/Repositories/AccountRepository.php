<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Read access to the locally cached WHM account data.
 *
 * The dashboard and accounts table read exclusively from these cached tables;
 * the live WHM API is only touched during synchronisation. This keeps page
 * loads fast and resilient to WHM outages.
 */
final class AccountRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * High-level dashboard counters computed from cached data.
     *
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        $row = $this->db->first(
            'SELECT
                COUNT(*)                                             AS total,
                SUM(CASE WHEN suspended = 1 THEN 1 ELSE 0 END)       AS suspended,
                SUM(CASE WHEN suspended = 0 THEN 1 ELSE 0 END)       AS active
             FROM whm_accounts
             WHERE deleted_at IS NULL'
        ) ?? [];

        $usage = $this->db->first(
            'SELECT
                COALESCE(SUM(u.disk_used_mb), 0)      AS disk_used,
                COALESCE(SUM(u.disk_limit_mb), 0)     AS disk_limit,
                COALESCE(SUM(u.bandwidth_used_mb), 0) AS bw_used,
                SUM(CASE WHEN u.disk_limit_mb > 0 AND (u.disk_used_mb / u.disk_limit_mb) >= 0.8 THEN 1 ELSE 0 END)      AS high_disk,
                SUM(CASE WHEN u.bandwidth_limit_mb > 0 AND (u.bandwidth_used_mb / u.bandwidth_limit_mb) >= 0.8 THEN 1 ELSE 0 END) AS high_bw
             FROM whm_account_usage u
             JOIN whm_accounts a ON a.id = u.account_id AND a.deleted_at IS NULL'
        ) ?? [];

        $diskUsed  = (float) ($usage['disk_used'] ?? 0);
        $diskLimit = (float) ($usage['disk_limit'] ?? 0);

        return [
            'total'         => (int) ($row['total'] ?? 0),
            'active'        => (int) ($row['active'] ?? 0),
            'suspended'     => (int) ($row['suspended'] ?? 0),
            'disk_used_mb'  => $diskUsed,
            'disk_limit_mb' => $diskLimit,
            'disk_percent'  => $diskLimit > 0 ? round(($diskUsed / $diskLimit) * 100, 1) : 0.0,
            'bw_used_mb'    => (float) ($usage['bw_used'] ?? 0),
            'high_disk'     => (int) ($usage['high_disk'] ?? 0),
            'high_bw'       => (int) ($usage['high_bw'] ?? 0),
        ];
    }

    public function packageCount(): int
    {
        $row = $this->db->first('SELECT COUNT(*) AS c FROM whm_packages WHERE deleted_at IS NULL');
        return (int) ($row['c'] ?? 0);
    }

    /**
     * SSL certificate status counters.
     *
     * @return array{valid:int, expiring:int, issues:int}
     */
    public function sslSummary(): array
    {
        $row = $this->db->first(
            "SELECT
                SUM(CASE WHEN status = 'valid' AND days_remaining > 30 THEN 1 ELSE 0 END) AS valid,
                SUM(CASE WHEN status = 'valid' AND days_remaining <= 30 THEN 1 ELSE 0 END) AS expiring,
                SUM(CASE WHEN status IN ('expired','invalid','missing') THEN 1 ELSE 0 END) AS issues
             FROM whm_ssl_certificates"
        ) ?? [];

        return [
            'valid'    => (int) ($row['valid'] ?? 0),
            'expiring' => (int) ($row['expiring'] ?? 0),
            'issues'   => (int) ($row['issues'] ?? 0),
        ];
    }

    public function lastSyncAt(): ?string
    {
        $row = $this->db->first(
            "SELECT MAX(finished_at) AS ts FROM whm_sync_runs WHERE status = 'completed'"
        );
        return $row['ts'] ?? null;
    }
}
