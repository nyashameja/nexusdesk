<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\AuditRepositoryInterface;

final class MySqlAuditRepository extends MySqlRepository implements AuditRepositoryInterface
{
    public function record(array $data): void
    {
        if (isset($data['ip_address'])) {
            $data['ip_address'] = $this->ipToBinary((string) $data['ip_address']);
        }
        foreach (['old_values', 'new_values'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $data[$key] = json_encode($data[$key], JSON_UNESCAPED_SLASHES);
            }
        }
        $this->db->insert('audit_logs', $data);
    }

    public function paginate(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        return $this->db->select(
            'SELECT a.*, TRIM(CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\'))) AS actor_name
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC LIMIT ? OFFSET ?',
            [$perPage, $offset]
        );
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM audit_logs');
    }
}
