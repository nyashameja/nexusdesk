<?php

declare(strict_types=1);

namespace App\Repositories\MySql;

use App\Repositories\Contracts\RoleRepositoryInterface;

final class MySqlRoleRepository extends MySqlRepository implements RoleRepositoryInterface
{
    public function all(): array
    {
        return $this->db->select(
            'SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS user_count
             FROM roles r ORDER BY r.id'
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne('SELECT * FROM roles WHERE slug = ?', [$slug]);
    }

    public function allPermissions(): array
    {
        return $this->db->select('SELECT * FROM permissions ORDER BY module, slug');
    }
}
