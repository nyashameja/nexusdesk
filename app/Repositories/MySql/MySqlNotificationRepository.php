<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\NotificationRepositoryInterface;

final class MySqlNotificationRepository extends MySqlRepository implements NotificationRepositoryInterface
{
    public function create(array $data): int
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data'], JSON_UNESCAPED_SLASHES);
        }
        return $this->db->insert('notifications', $data);
    }

    public function forUser(int $userId, bool $unreadOnly = false, int $limit = 20): array
    {
        $sql = 'SELECT * FROM notifications WHERE user_id = ?';
        if ($unreadOnly) {
            $sql .= ' AND read_at IS NULL';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ?';
        return $this->db->select($sql, [$userId, $limit]);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    public function markRead(int $id, int $userId): void
    {
        $this->db->run(
            'UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL',
            [$id, $userId]
        );
    }

    public function markAllRead(int $userId): void
    {
        $this->db->run(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }
}
