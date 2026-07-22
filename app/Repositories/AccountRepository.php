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
            "SELECT MAX(finished_at) AS ts FROM whm_sync_runs WHERE status IN ('completed','partial')"
        );
        return $row['ts'] ?? null;
    }

    /**
     * Look up a cached account id by server + username.
     */
    public function findIdByUsername(int $serverId, string $username): ?int
    {
        $row = $this->db->first(
            'SELECT id FROM whm_accounts WHERE server_id = ? AND username = ? LIMIT 1',
            [$serverId, $username]
        );
        return $row === null ? null : (int) $row['id'];
    }

    /**
     * Look up a cached (non-deleted) account id by server + primary domain.
     */
    public function findIdByDomain(int $serverId, string $domain): ?int
    {
        $row = $this->db->first(
            'SELECT id FROM whm_accounts WHERE server_id = ? AND domain = ? AND deleted_at IS NULL LIMIT 1',
            [$serverId, $domain]
        );
        return $row === null ? null : (int) $row['id'];
    }

    /**
     * Insert or update a cached account. Returns [id, created?].
     *
     * @param array<string, mixed> $data Normalised account fields.
     * @return array{id:int, created:bool}
     */
    public function upsert(int $serverId, array $data): array
    {
        $existingId = $this->findIdByUsername($serverId, (string) $data['username']);

        if ($existingId !== null) {
            $this->db->execute(
                'UPDATE whm_accounts SET
                    domain = ?, owner = ?, email = ?, package = ?, ip_address = ?,
                    theme = ?, locale = ?, suspended = ?, suspend_reason = ?,
                    whm_created_at = ?, last_synced_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP(),
                    deleted_at = NULL
                 WHERE id = ?',
                [
                    $data['domain'], $data['owner'], $data['email'], $data['package'], $data['ip_address'],
                    $data['theme'], $data['locale'], $data['suspended'], $data['suspend_reason'],
                    $data['whm_created_at'], $existingId,
                ]
            );
            return ['id' => $existingId, 'created' => false];
        }

        $this->db->execute(
            'INSERT INTO whm_accounts
                (server_id, username, domain, owner, email, package, ip_address, theme, locale,
                 suspended, suspend_reason, whm_created_at, last_synced_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [
                $serverId, $data['username'], $data['domain'], $data['owner'], $data['email'],
                $data['package'], $data['ip_address'], $data['theme'], $data['locale'],
                $data['suspended'], $data['suspend_reason'], $data['whm_created_at'],
            ]
        );
        return ['id' => $this->db->lastInsertId(), 'created' => true];
    }

    /**
     * Upsert disk usage for an account (bandwidth merged separately).
     */
    public function upsertDiskUsage(int $accountId, int $diskUsedMb, int $diskLimitMb): void
    {
        $this->db->execute(
            'INSERT INTO whm_account_usage (account_id, disk_used_mb, disk_limit_mb, captured_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE disk_used_mb = VALUES(disk_used_mb),
                                     disk_limit_mb = VALUES(disk_limit_mb),
                                     captured_at = UTC_TIMESTAMP()',
            [$accountId, $diskUsedMb, $diskLimitMb]
        );
    }

    /**
     * Merge bandwidth figures into an existing usage row (by username).
     */
    public function updateBandwidthByUsername(int $serverId, string $username, int $usedMb, int $limitMb): bool
    {
        $accountId = $this->findIdByUsername($serverId, $username);
        if ($accountId === null) {
            return false;
        }

        $this->db->execute(
            'INSERT INTO whm_account_usage (account_id, bandwidth_used_mb, bandwidth_limit_mb, captured_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE bandwidth_used_mb = VALUES(bandwidth_used_mb),
                                     bandwidth_limit_mb = VALUES(bandwidth_limit_mb),
                                     captured_at = UTC_TIMESTAMP()',
            [$accountId, $usedMb, $limitMb]
        );
        return true;
    }

    /**
     * Set the cached SSL status label on an account.
     */
    public function setSslStatusByDomain(int $serverId, string $domain, string $status): void
    {
        $this->db->execute(
            'UPDATE whm_accounts SET ssl_status = ? WHERE server_id = ? AND domain = ?',
            [$status, $serverId, $domain]
        );
    }

    /**
     * Soft-delete cached accounts on a server whose username is not in the
     * provided keep-list (i.e. no longer visible to WHM).
     *
     * @param array<int, string> $keepUsernames
     */
    public function softDeleteMissing(int $serverId, array $keepUsernames): int
    {
        if ($keepUsernames === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($keepUsernames), '?'));
        return $this->db->execute(
            "UPDATE whm_accounts
                SET deleted_at = UTC_TIMESTAMP()
              WHERE server_id = ? AND deleted_at IS NULL AND username NOT IN ($placeholders)",
            array_merge([$serverId], $keepUsernames)
        );
    }
}
