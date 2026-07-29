<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Data access for the local domain registry. Version 1 has no registrar API —
 * all data is entered/maintained locally.
 */
final class DomainRepository
{
    private const FILLABLE = [
        'domain', 'client_id', 'account_id', 'registrar', 'registered_at',
        'expires_at', 'auto_renew', 'nameserver1', 'nameserver2', 'status',
        'renewal_cost', 'client_price', 'notes',
    ];

    public function __construct(private Database $db)
    {
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function paginate(string $search, string $status, int $page, int $perPage): array
    {
        $conditions = ['d.deleted_at IS NULL'];
        $args = [];

        if ($search !== '') {
            $conditions[] = '(d.domain LIKE ? OR d.registrar LIKE ?)';
            $like = '%' . $search . '%';
            array_push($args, $like, $like);
        }
        if ($status !== '') {
            $conditions[] = 'd.status = ?';
            $args[] = $status;
        }

        $where   = implode(' AND ', $conditions);
        $page    = max(1, $page);
        $perPage = max(5, min($perPage, 100));
        $offset  = ($page - 1) * $perPage;

        $total = (int) ($this->db->first("SELECT COUNT(*) AS c FROM domains d WHERE {$where}", $args)['c'] ?? 0);

        $rows = $this->db->all(
            "SELECT d.*, c.company_name, c.first_name, c.last_name,
                    DATEDIFF(d.expires_at, UTC_DATE()) AS days_to_expiry
               FROM domains d
          LEFT JOIN clients c ON c.id = d.client_id
              WHERE {$where}
           ORDER BY (d.expires_at IS NULL), d.expires_at ASC
              LIMIT {$perPage} OFFSET {$offset}",
            $args
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->first(
            "SELECT d.*, c.company_name, c.first_name, c.last_name, a.username AS account_username, a.domain AS account_domain,
                    DATEDIFF(d.expires_at, UTC_DATE()) AS days_to_expiry
               FROM domains d
          LEFT JOIN clients c ON c.id = d.client_id
          LEFT JOIN whm_accounts a ON a.id = d.account_id
              WHERE d.id = ? AND d.deleted_at IS NULL LIMIT 1",
            [$id]
        );
    }

    /**
     * Counts by status plus expiry-window buckets, for the module header.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $rows = $this->db->all('SELECT status, COUNT(*) AS c FROM domains WHERE deleted_at IS NULL GROUP BY status');
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['status']] = (int) $r['c'];
        }

        $windows = $this->db->first(
            "SELECT
                SUM(CASE WHEN DATEDIFF(expires_at, UTC_DATE()) < 0 THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN DATEDIFF(expires_at, UTC_DATE()) BETWEEN 0 AND 30 THEN 1 ELSE 0 END) AS within30
             FROM domains WHERE deleted_at IS NULL AND expires_at IS NOT NULL"
        ) ?? [];
        $out['_expired']  = (int) ($windows['expired'] ?? 0);
        $out['_within30'] = (int) ($windows['within30'] ?? 0);

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $fields = $this->filter($data);
        $columns = array_keys($fields);
        $this->db->execute(
            'INSERT INTO domains (`' . implode('`,`', $columns) . '`, created_at, updated_at) VALUES ('
                . implode(',', array_fill(0, count($columns), '?')) . ', UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            array_values($fields)
        );
        return $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $fields = $this->filter($data);
        if ($fields === []) {
            return;
        }
        $set = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($fields)));
        $params = array_values($fields);
        $params[] = $id;
        $this->db->execute("UPDATE domains SET {$set}, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL", $params);
    }

    public function softDelete(int $id): void
    {
        $this->db->execute('UPDATE domains SET deleted_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    /**
     * Dashboard summary of upcoming/expired domains.
     *
     * @return array{expired:int, within30:int, within60:int, soon:array<int,array<string,mixed>>}
     */
    public function expirySummary(int $listLimit = 6): array
    {
        $counts = $this->db->first(
            "SELECT
                SUM(CASE WHEN DATEDIFF(expires_at, UTC_DATE()) < 0 THEN 1 ELSE 0 END)             AS expired,
                SUM(CASE WHEN DATEDIFF(expires_at, UTC_DATE()) BETWEEN 0 AND 30 THEN 1 ELSE 0 END) AS within30,
                SUM(CASE WHEN DATEDIFF(expires_at, UTC_DATE()) BETWEEN 0 AND 60 THEN 1 ELSE 0 END) AS within60
             FROM domains WHERE deleted_at IS NULL AND expires_at IS NOT NULL"
        ) ?? [];

        $listLimit = max(1, min($listLimit, 20));
        $soon = $this->db->all(
            "SELECT id, domain, expires_at, status, DATEDIFF(expires_at, UTC_DATE()) AS days_to_expiry
               FROM domains
              WHERE deleted_at IS NULL AND expires_at IS NOT NULL
                AND DATEDIFF(expires_at, UTC_DATE()) <= 60
           ORDER BY expires_at ASC
              LIMIT {$listLimit}"
        );

        return [
            'expired'  => (int) ($counts['expired'] ?? 0),
            'within30' => (int) ($counts['within30'] ?? 0),
            'within60' => (int) ($counts['within60'] ?? 0),
            'soon'     => $soon,
        ];
    }

    /**
     * All non-deleted domains, minimal fields, for the expiry checker.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allForCheck(): array
    {
        return $this->db->all(
            'SELECT id, domain, expires_at, registrar, status FROM domains WHERE deleted_at IS NULL ORDER BY id'
        );
    }

    /**
     * Apply an expiry lookup result: always set expiry + status; only overwrite
     * the registrar when the lookup returned one.
     */
    public function applyLookup(int $id, string $expiresAt, ?string $registrar, string $status): void
    {
        if ($registrar !== null && $registrar !== '') {
            $this->db->execute(
                'UPDATE domains SET expires_at = ?, registrar = ?, status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL',
                [$expiresAt, $registrar, $status, $id]
            );
        } else {
            $this->db->execute(
                'UPDATE domains SET expires_at = ?, status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL',
                [$expiresAt, $status, $id]
            );
        }
    }

    /**
     * Client id => display name, for the form dropdown.
     *
     * @return array<int, string>
     */
    public function clientOptions(): array
    {
        $rows = $this->db->all(
            "SELECT id, COALESCE(NULLIF(company_name,''), CONCAT_WS(' ', first_name, last_name)) AS name
               FROM clients WHERE deleted_at IS NULL ORDER BY name LIMIT 1000"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = (string) ($r['name'] ?: 'Client #' . $r['id']);
        }
        return $out;
    }

    /**
     * Account id => domain (username), for the form dropdown.
     *
     * @return array<int, string>
     */
    public function accountOptions(): array
    {
        $rows = $this->db->all(
            'SELECT id, domain, username FROM whm_accounts WHERE deleted_at IS NULL ORDER BY domain LIMIT 1000'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r['domain'] . ' (' . $r['username'] . ')';
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        $out = [];
        foreach (self::FILLABLE as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if (in_array($field, ['client_id', 'account_id'], true)) {
                $out[$field] = $value !== '' && $value !== null ? (int) $value : null;
            } elseif (in_array($field, ['registered_at', 'expires_at'], true)) {
                $out[$field] = $value !== '' && $value !== null ? $value : null;
            } elseif (in_array($field, ['renewal_cost', 'client_price'], true)) {
                $out[$field] = $value !== '' && $value !== null ? (float) $value : null;
            } elseif ($field === 'auto_renew') {
                $out[$field] = (int) ((bool) $value);
            } else {
                $out[$field] = is_string($value) && trim($value) === '' ? null : $value;
            }
        }
        return $out;
    }
}
