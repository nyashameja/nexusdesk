<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Data access for uptime monitors and their check history.
 */
final class UptimeRepository
{
    private const FILLABLE = ['client_id', 'account_id', 'label', 'url', 'expected_status', 'enabled'];

    public function __construct(private Database $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->all(
            "SELECT m.*, COALESCE(NULLIF(c.company_name,''), CONCAT_WS(' ', c.first_name, c.last_name)) AS client_name
               FROM uptime_monitors m
          LEFT JOIN clients c ON c.id = m.client_id
           ORDER BY (m.current_status = 'offline') DESC, m.label ASC"
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function enabled(): array
    {
        return $this->db->all('SELECT * FROM uptime_monitors WHERE enabled = 1 ORDER BY id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM uptime_monitors WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $rows = $this->db->all('SELECT current_status, COUNT(*) AS c FROM uptime_monitors WHERE enabled = 1 GROUP BY current_status');
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['current_status']] = (int) $r['c'];
        }
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
            'INSERT INTO uptime_monitors (`' . implode('`,`', $columns) . '`, created_at, updated_at) VALUES ('
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
        $this->db->execute("UPDATE uptime_monitors SET {$set}, updated_at = UTC_TIMESTAMP() WHERE id = ?", $params);
    }

    /**
     * Record the outcome of a probe: append a check row and update the monitor.
     *
     * @param array{status:string, status_code:?int, response_ms:int, is_up:bool, error:?string} $result
     */
    public function recordCheck(int $monitorId, array $result): void
    {
        $this->db->execute(
            'INSERT INTO uptime_checks (monitor_id, status_code, response_ms, is_up, error, checked_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$monitorId, $result['status_code'], $result['response_ms'], $result['is_up'] ? 1 : 0, $result['error']]
        );

        $failureExpr = $result['is_up'] ? '0' : 'failure_count + 1';
        $successClause = $result['is_up'] ? ', last_success_at = UTC_TIMESTAMP()' : '';

        $this->db->execute(
            "UPDATE uptime_monitors
                SET current_status = ?, last_status_code = ?, last_response_ms = ?,
                    failure_count = {$failureExpr}, last_checked_at = UTC_TIMESTAMP(){$successClause},
                    updated_at = UTC_TIMESTAMP()
              WHERE id = ?",
            [$result['status'], $result['status_code'], $result['response_ms'], $monitorId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentChecks(int $monitorId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));
        return $this->db->all(
            "SELECT * FROM uptime_checks WHERE monitor_id = ? ORDER BY id DESC LIMIT {$limit}",
            [$monitorId]
        );
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
            if ($f === 'client_id' || $f === 'account_id') {
                $out[$f] = $v !== '' && $v !== null ? (int) $v : null;
            } elseif ($f === 'expected_status') {
                $out[$f] = (int) ($v ?: 200);
            } elseif ($f === 'enabled') {
                $out[$f] = (int) ((bool) $v);
            } else {
                $out[$f] = is_string($v) && trim($v) === '' ? null : $v;
            }
        }
        return $out;
    }
}
