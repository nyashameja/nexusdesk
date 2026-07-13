<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\DepartmentRepositoryInterface;

final class MySqlDepartmentRepository extends MySqlRepository implements DepartmentRepositoryInterface
{
    public function all(bool $publicOnly = false): array
    {
        $where = $publicOnly ? 'WHERE d.is_active = 1 AND d.is_public = 1' : 'WHERE d.is_active = 1';
        return $this->db->select(
            "SELECT d.*, m.first_name AS manager_first, m.last_name AS manager_last,
                    (SELECT COUNT(*) FROM tickets t WHERE t.department_id = d.id AND t.deleted_at IS NULL) AS ticket_count
             FROM departments d
             LEFT JOIN users m ON m.id = d.manager_id
             $where ORDER BY d.sort_order, d.name"
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM departments WHERE id = ?', [$id]);
    }

    public function create(array $data): int
    {
        return $this->db->insert('departments', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('departments', $data, ['id' => $id]);
    }

    public function defaultSlaPolicyId(int $departmentId): ?int
    {
        $value = $this->db->scalar('SELECT sla_policy_id FROM departments WHERE id = ?', [$departmentId]);
        return $value !== null && $value !== false ? (int) $value : null;
    }
}
