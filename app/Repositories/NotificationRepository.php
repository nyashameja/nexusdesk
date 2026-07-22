<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Application notifications. user_id NULL = broadcast (visible to all users).
 */
final class NotificationRepository
{
    public function __construct(private Database $db)
    {
    }

    public function create(string $type, string $title, string $body, string $severity = 'info', ?int $userId = null): void
    {
        $this->db->execute(
            'INSERT INTO notifications (user_id, type, title, body, severity, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$userId, $type, mb_substr($title, 0, 190), mb_substr($body, 0, 500), $severity]
        );
    }

    /**
     * De-duplicate: only create if no unread notification of the same type/title
     * already exists (avoids repeat alerts every cron run).
     */
    public function createOnce(string $type, string $title, string $body, string $severity = 'info'): void
    {
        $exists = $this->db->first(
            'SELECT id FROM notifications WHERE type = ? AND title = ? AND read_at IS NULL LIMIT 1',
            [$type, mb_substr($title, 0, 190)]
        );
        if ($exists === null) {
            $this->create($type, $title, $body, $severity);
        }
    }

    public function unreadCount(): int
    {
        $row = $this->db->first('SELECT COUNT(*) AS c FROM notifications WHERE read_at IS NULL');
        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));
        return $this->db->all("SELECT * FROM notifications ORDER BY id DESC LIMIT {$limit}");
    }

    public function markAllRead(): void
    {
        $this->db->execute('UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE read_at IS NULL');
    }
}
