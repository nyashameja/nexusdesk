<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Persists audit-trail entries. Sensitive values must never be passed in.
 */
final class AuditLogRepository
{
    public function __construct(private Database $db)
    {
    }

    public function log(
        ?int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        string $description,
        string $ip,
        string $userAgent,
    ): void {
        $this->db->execute(
            'INSERT INTO activity_logs
                (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$userId, $action, $entityType, $entityId, $description, $ip, $userAgent]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, min($limit, 500));
        return $this->db->all(
            'SELECT a.*, u.name AS user_name
               FROM activity_logs a
          LEFT JOIN users u ON u.id = a.user_id
           ORDER BY a.created_at DESC
              LIMIT ' . $limit
        );
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function paginate(string $action, int $page, int $perPage): array
    {
        $conditions = ['1=1'];
        $args = [];
        if ($action !== '') {
            $conditions[] = 'a.action = ?';
            $args[] = $action;
        }
        $where   = implode(' AND ', $conditions);
        $page    = max(1, $page);
        $perPage = max(5, min($perPage, 100));
        $offset  = ($page - 1) * $perPage;

        $total = (int) ($this->db->first("SELECT COUNT(*) AS c FROM activity_logs a WHERE {$where}", $args)['c'] ?? 0);

        $rows = $this->db->all(
            "SELECT a.*, u.name AS user_name
               FROM activity_logs a
          LEFT JOIN users u ON u.id = a.user_id
              WHERE {$where}
           ORDER BY a.id DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $args
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Distinct action names for the filter dropdown.
     *
     * @return array<int, string>
     */
    public function distinctActions(): array
    {
        $rows = $this->db->all('SELECT DISTINCT action FROM activity_logs ORDER BY action');
        return array_map(static fn ($r): string => (string) $r['action'], $rows);
    }
}
