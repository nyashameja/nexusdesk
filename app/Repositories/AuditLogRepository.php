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
}
