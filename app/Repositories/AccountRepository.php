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

    // ---------------------------------------------------------------------
    // Dashboard chart data (all read-only, from the cache)
    // ---------------------------------------------------------------------

    /**
     * Top accounts by disk usage.
     *
     * @return array<int, array{domain:string, used:int, limit:int, percent:float}>
     */
    public function topDiskUsage(int $limit = 8): array
    {
        $limit = max(1, min($limit, 25));
        $rows = $this->db->all(
            "SELECT a.domain, COALESCE(u.disk_used_mb,0) AS used, COALESCE(u.disk_limit_mb,0) AS lim
               FROM whm_accounts a
          LEFT JOIN whm_account_usage u ON u.account_id = a.id
              WHERE a.deleted_at IS NULL
           ORDER BY used DESC
              LIMIT {$limit}"
        );
        return array_map($this->usageRow(...), $rows);
    }

    /**
     * Top accounts by bandwidth usage.
     *
     * @return array<int, array{domain:string, used:int, limit:int, percent:float}>
     */
    public function topBandwidthUsage(int $limit = 8): array
    {
        $limit = max(1, min($limit, 25));
        $rows = $this->db->all(
            "SELECT a.domain, COALESCE(u.bandwidth_used_mb,0) AS used, COALESCE(u.bandwidth_limit_mb,0) AS lim
               FROM whm_accounts a
          LEFT JOIN whm_account_usage u ON u.account_id = a.id
              WHERE a.deleted_at IS NULL
           ORDER BY used DESC
              LIMIT {$limit}"
        );
        return array_map($this->usageRow(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{domain:string, used:int, limit:int, percent:float}
     */
    private function usageRow(array $row): array
    {
        $used  = (int) $row['used'];
        $limit = (int) $row['lim'];
        return [
            'domain'  => (string) $row['domain'],
            'used'    => $used,
            'limit'   => $limit,
            'percent' => $limit > 0 ? round(($used / $limit) * 100, 1) : 0.0,
        ];
    }

    /**
     * Account counts grouped by package.
     *
     * @return array<int, array{package:string, total:int}>
     */
    public function countByPackage(): array
    {
        $rows = $this->db->all(
            "SELECT COALESCE(NULLIF(package,''),'Unassigned') AS package, COUNT(*) AS total
               FROM whm_accounts
              WHERE deleted_at IS NULL
           GROUP BY package
           ORDER BY total DESC"
        );
        return array_map(static fn (array $r): array => [
            'package' => (string) $r['package'],
            'total'   => (int) $r['total'],
        ], $rows);
    }

    /**
     * SSL status distribution across cached certificates.
     *
     * @return array<string, int>
     */
    public function sslDistribution(): array
    {
        $rows = $this->db->all(
            'SELECT status, COUNT(*) AS total FROM whm_ssl_certificates GROUP BY status'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['status']] = (int) $r['total'];
        }
        return $out;
    }

    /**
     * Accounts created per month (last 12 months with data).
     *
     * @return array<int, array{month:string, total:int}>
     */
    public function createdOverTime(): array
    {
        $rows = $this->db->all(
            "SELECT DATE_FORMAT(whm_created_at, '%Y-%m') AS month, COUNT(*) AS total
               FROM whm_accounts
              WHERE deleted_at IS NULL AND whm_created_at IS NOT NULL
           GROUP BY month
           ORDER BY month ASC
              LIMIT 24"
        );
        return array_map(static fn (array $r): array => [
            'month' => (string) $r['month'],
            'total' => (int) $r['total'],
        ], $rows);
    }

    /**
     * Accounts requiring attention (high usage, suspended, SSL issues).
     *
     * @return array<int, array<string, mixed>>
     */
    public function attentionList(int $limit = 12): array
    {
        $limit = max(1, min($limit, 100));
        return $this->db->all(
            "SELECT a.id, a.domain, a.username, a.suspended, a.ssl_status,
                    COALESCE(u.disk_used_mb,0) AS disk_used, COALESCE(u.disk_limit_mb,0) AS disk_limit,
                    COALESCE(u.bandwidth_used_mb,0) AS bw_used, COALESCE(u.bandwidth_limit_mb,0) AS bw_limit,
                    (SELECT MIN(s.days_remaining) FROM whm_ssl_certificates s WHERE s.account_id = a.id) AS ssl_days
               FROM whm_accounts a
          LEFT JOIN whm_account_usage u ON u.account_id = a.id
              WHERE a.deleted_at IS NULL
                AND (
                    a.suspended = 1
                    OR (u.disk_limit_mb > 0 AND u.disk_used_mb / u.disk_limit_mb >= 0.8)
                    OR (u.bandwidth_limit_mb > 0 AND u.bandwidth_used_mb / u.bandwidth_limit_mb >= 0.8)
                    OR a.ssl_status IN ('expired','invalid','missing','expiring')
                )
           ORDER BY a.suspended DESC, (u.disk_used_mb / NULLIF(u.disk_limit_mb,0)) DESC
              LIMIT {$limit}"
        );
    }

    // ---------------------------------------------------------------------
    // Filtered, sorted, paginated listing for the accounts table
    // ---------------------------------------------------------------------

    /** Sortable columns, whitelisted to prevent SQL injection via sort key. */
    private const SORTABLE = [
        'domain'    => 'a.domain',
        'package'   => 'a.package',
        'disk'      => 'disk_percent',
        'bandwidth' => 'bw_percent',
        'created'   => 'a.whm_created_at',
        'status'    => 'a.suspended',
    ];

    /**
     * @param array<string, string> $filters
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function search(array $filters, string $sort, string $dir, int $page, int $perPage): array
    {
        $conditions = ['a.deleted_at IS NULL'];
        $args = [];

        if (($q = trim($filters['q'] ?? '')) !== '') {
            $conditions[] = '(a.domain LIKE ? OR a.username LIKE ? OR a.email LIKE ?)';
            $like = '%' . $q . '%';
            array_push($args, $like, $like, $like);
        }
        if (($pkg = trim($filters['package'] ?? '')) !== '') {
            $conditions[] = 'a.package = ?';
            $args[] = $pkg;
        }
        if (($status = $filters['status'] ?? '') === 'active') {
            $conditions[] = 'a.suspended = 0';
        } elseif ($status === 'suspended') {
            $conditions[] = 'a.suspended = 1';
        }
        if (($filters['disk'] ?? '') === 'high') {
            $conditions[] = 'u.disk_limit_mb > 0 AND u.disk_used_mb / u.disk_limit_mb >= 0.8';
        }
        if (($filters['bandwidth'] ?? '') === 'high') {
            $conditions[] = 'u.bandwidth_limit_mb > 0 AND u.bandwidth_used_mb / u.bandwidth_limit_mb >= 0.8';
        }
        if (($ssl = $filters['ssl'] ?? '') !== '') {
            if ($ssl === 'attention') {
                $conditions[] = "a.ssl_status IN ('expired','invalid','missing','expiring')";
            } else {
                $conditions[] = 'a.ssl_status = ?';
                $args[] = $ssl;
            }
        }
        if (($linked = $filters['linked'] ?? '') === 'linked') {
            $conditions[] = 'a.client_id IS NOT NULL';
        } elseif ($linked === 'unlinked') {
            $conditions[] = 'a.client_id IS NULL';
        }

        $where = implode(' AND ', $conditions);

        $sortColumn = self::SORTABLE[$sort] ?? 'a.domain';
        $sortDir    = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        $page    = max(1, $page);
        $perPage = max(5, min($perPage, 100));
        $offset  = ($page - 1) * $perPage;

        $total = (int) ($this->db->first(
            "SELECT COUNT(*) AS c
               FROM whm_accounts a
          LEFT JOIN whm_account_usage u ON u.account_id = a.id
              WHERE {$where}",
            $args
        )['c'] ?? 0);

        $rows = $this->db->all(
            "SELECT a.*, c.company_name, c.first_name, c.last_name,
                    COALESCE(u.disk_used_mb,0) AS disk_used_mb, COALESCE(u.disk_limit_mb,0) AS disk_limit_mb,
                    COALESCE(u.bandwidth_used_mb,0) AS bandwidth_used_mb, COALESCE(u.bandwidth_limit_mb,0) AS bandwidth_limit_mb,
                    CASE WHEN u.disk_limit_mb > 0 THEN u.disk_used_mb / u.disk_limit_mb ELSE 0 END AS disk_percent,
                    CASE WHEN u.bandwidth_limit_mb > 0 THEN u.bandwidth_used_mb / u.bandwidth_limit_mb ELSE 0 END AS bw_percent
               FROM whm_accounts a
          LEFT JOIN whm_account_usage u ON u.account_id = a.id
          LEFT JOIN clients c ON c.id = a.client_id
              WHERE {$where}
           ORDER BY {$sortColumn} {$sortDir}, a.domain ASC
              LIMIT {$perPage} OFFSET {$offset}",
            $args
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Distinct package names present in the cache (for the filter dropdown).
     *
     * @return array<int, string>
     */
    public function distinctPackages(): array
    {
        $rows = $this->db->all(
            "SELECT DISTINCT package FROM whm_accounts
              WHERE deleted_at IS NULL AND package IS NOT NULL AND package <> ''
           ORDER BY package"
        );
        return array_map(static fn (array $r): string => (string) $r['package'], $rows);
    }

    /**
     * Full account detail with usage, SSL certificates and linked client.
     *
     * @return array<string, mixed>|null
     */
    public function detail(int $id): ?array
    {
        $account = $this->db->first(
            "SELECT a.*, c.id AS client_id_linked, c.company_name, c.first_name, c.last_name, c.primary_email AS client_email,
                    u.disk_used_mb, u.disk_limit_mb, u.bandwidth_used_mb, u.bandwidth_limit_mb, u.email_accounts, u.captured_at,
                    s.name AS server_name, s.hostname AS server_hostname
               FROM whm_accounts a
          LEFT JOIN clients c ON c.id = a.client_id
          LEFT JOIN whm_account_usage u ON u.account_id = a.id
          LEFT JOIN servers s ON s.id = a.server_id
              WHERE a.id = ? AND a.deleted_at IS NULL
              LIMIT 1",
            [$id]
        );

        if ($account === null) {
            return null;
        }

        $account['ssl_certs'] = $this->db->all(
            'SELECT * FROM whm_ssl_certificates WHERE account_id = ? ORDER BY valid_to ASC',
            [$id]
        );

        $account['subscription'] = $this->db->first(
            "SELECT * FROM subscriptions WHERE account_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1",
            [$id]
        );

        return $account;
    }
}
