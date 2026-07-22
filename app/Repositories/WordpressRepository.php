<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Data access for the local WordPress site registry. Version 1 stores data
 * manually (the read-only WHM token cannot report WordPress details); entries
 * are flagged as manually entered.
 */
final class WordpressRepository
{
    private const FILLABLE = [
        'client_id', 'account_id', 'name', 'url', 'staging_url', 'wp_status',
        'wp_version', 'php_version', 'last_backup_at', 'last_update_at',
        'maintenance_plan', 'maintenance_fee', 'notes',
    ];

    public function __construct(private Database $db)
    {
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function paginate(string $search, string $status, int $page, int $perPage): array
    {
        $conditions = ['w.deleted_at IS NULL'];
        $args = [];
        if ($search !== '') {
            $conditions[] = '(w.name LIKE ? OR w.url LIKE ?)';
            $like = '%' . $search . '%';
            array_push($args, $like, $like);
        }
        if ($status !== '') {
            $conditions[] = 'w.wp_status = ?';
            $args[] = $status;
        }
        $where   = implode(' AND ', $conditions);
        $page    = max(1, $page);
        $perPage = max(5, min($perPage, 100));
        $offset  = ($page - 1) * $perPage;

        $total = (int) ($this->db->first("SELECT COUNT(*) AS c FROM wordpress_sites w WHERE {$where}", $args)['c'] ?? 0);

        $rows = $this->db->all(
            "SELECT w.*, COALESCE(NULLIF(c.company_name,''), CONCAT_WS(' ', c.first_name, c.last_name)) AS client_name
               FROM wordpress_sites w
          LEFT JOIN clients c ON c.id = w.client_id
              WHERE {$where}
           ORDER BY w.name ASC
              LIMIT {$perPage} OFFSET {$offset}",
            $args
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $rows = $this->db->all('SELECT wp_status, COUNT(*) AS c FROM wordpress_sites WHERE deleted_at IS NULL GROUP BY wp_status');
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['wp_status']] = (int) $r['c'];
        }
        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->first(
            "SELECT w.*, COALESCE(NULLIF(c.company_name,''), CONCAT_WS(' ', c.first_name, c.last_name)) AS client_name,
                    a.domain AS account_domain
               FROM wordpress_sites w
          LEFT JOIN clients c ON c.id = w.client_id
          LEFT JOIN whm_accounts a ON a.id = w.account_id
              WHERE w.id = ? AND w.deleted_at IS NULL LIMIT 1",
            [$id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $fields = $this->filter($data);
        $fields['manual_entry'] = 1;
        $columns = array_keys($fields);
        $this->db->execute(
            'INSERT INTO wordpress_sites (`' . implode('`,`', $columns) . '`, created_at, updated_at) VALUES ('
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
        $this->db->execute("UPDATE wordpress_sites SET {$set}, updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL", $params);
    }

    /**
     * @return array<int, string>
     */
    public function clientOptions(): array
    {
        $rows = $this->db->all("SELECT id, COALESCE(NULLIF(company_name,''), CONCAT_WS(' ', first_name, last_name)) AS name FROM clients WHERE deleted_at IS NULL ORDER BY name LIMIT 1000");
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = (string) ($r['name'] ?: 'Client #' . $r['id']);
        }
        return $out;
    }

    /**
     * @return array<int, string>
     */
    public function accountOptions(): array
    {
        $rows = $this->db->all('SELECT id, domain, username FROM whm_accounts WHERE deleted_at IS NULL ORDER BY domain LIMIT 1000');
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
        foreach (self::FILLABLE as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $v = $data[$f];
            if (in_array($f, ['client_id', 'account_id'], true)) {
                $out[$f] = $v !== '' && $v !== null ? (int) $v : null;
            } elseif (in_array($f, ['last_backup_at', 'last_update_at'], true)) {
                $out[$f] = $v !== '' && $v !== null ? $v : null;
            } elseif ($f === 'maintenance_fee') {
                $out[$f] = $v !== '' && $v !== null ? (float) $v : null;
            } else {
                $out[$f] = is_string($v) && trim($v) === '' ? null : $v;
            }
        }
        return $out;
    }
}
